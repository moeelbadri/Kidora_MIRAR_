#!/usr/bin/env python3
"""Run with python3 tests/integration.py. MySQL requires KIDORA_TEST_MYSQL=1
and an EMPTY dedicated KIDAURA_DB_NAME ending in _test. No databases are dropped.
Only fixtures use SQL; assertions exercise public HTTP endpoints.
"""
import concurrent.futures
import base64
import hashlib
import http.cookiejar
from html.parser import HTMLParser
import json
import os
from pathlib import Path
import re
import signal
import socket
import subprocess
import tempfile
import time
import urllib.error
import urllib.parse
import urllib.request

ROOT = Path(__file__).resolve().parents[1]


class AnchorLinks(HTMLParser):
    def __init__(self):
        super().__init__()
        self.hrefs = set()

    def handle_starttag(self, tag, attrs):
        if tag == 'a':
            self.hrefs.add(dict(attrs).get('href', ''))


class NoRedirect(urllib.request.HTTPRedirectHandler):
    def redirect_request(self, *args):
        return None


class Client:
    def __init__(self, base):
        self.base = base
        self.jar = http.cookiejar.CookieJar()
        self.http = urllib.request.build_opener(NoRedirect, urllib.request.HTTPCookieProcessor(self.jar))
        self.token = ''

    def request(self, path, data=None, csrf=True):
        if data is not None:
            data = dict(data)
            if csrf:
                data.setdefault('csrf_token', self.token)
            data = urllib.parse.urlencode(data).encode()
        try:
            response = self.http.open(self.base + path, data, timeout=20)
        except urllib.error.HTTPError as error:
            response = error
        body = response.read().decode('utf-8', errors='replace')
        match = re.search(r'name="csrf_token" value="([^"]+)"', body)
        if match:
            self.token = match[1]
        return response.code, body

    def cookie(self, name):
        return next((c.value for c in self.jar if c.name == name), None)


def main():
    with tempfile.TemporaryDirectory(prefix='kidora-tests-') as temp:
        env = dict(os.environ, KIDORA_TEST_ISOLATED='1', KIDORA_PRIVATE_DIR=temp,
                   KIDORA_SQLITE_PATH=temp + '/test.sqlite', PHP_CLI_SERVER_WORKERS='4')
        # Never inherit separate migration credentials aimed at another database.
        for key in list(env):
            if key.startswith(('KIDAURA_MIGRATION_DB_', 'KIDORA_MIGRATION_DB_')):
                del env[key]
        if os.environ.get('KIDORA_TEST_MYSQL') == '1':
            assert env.get('KIDAURA_DB_NAME', '').endswith('_test'), 'Require dedicated *_test database'
            env['KIDAURA_DB_DRIVER'] = 'mysql'
        else:
            env['KIDAURA_DB_DRIVER'] = 'sqlite'
        def php(*args, **kwargs):
            return subprocess.check_output(['php', *args], cwd=ROOT, env=env, **kwargs)
        def sql(query, params=()):
            return json.loads(php('tests/fixture.php', input=json.dumps({'sql': query, 'params': params}).encode()))
        def scalar(query, params=()):
            return next(iter(sql(query, params)[0].values()))
        php('scripts/migrate.php')
        assert scalar('SELECT COUNT(*) FROM children') == 0, 'Database must be empty'
        with socket.socket() as sock:
            sock.bind(('127.0.0.1', 0))
            port = sock.getsockname()[1]
        base = f'http://127.0.0.1:{port}'
        env['KIDORA_APP_URL'] = base
        log = open(temp + '/server.log', 'w+')
        server = subprocess.Popen(['php', '-d', 'session.save_path=' + temp, '-S', f'127.0.0.1:{port}', 'router.php'],
                                  cwd=ROOT, env=env, stdout=log, stderr=log, start_new_session=True)
        try:
            anon = Client(base)
            for _ in range(100):
                try:
                    if anon.request('/index.php')[0] == 200:
                        break
                except OSError:
                    pass
                time.sleep(.1)
            else:
                raise AssertionError('Server not ready')
            home_links = AnchorLinks()
            home_links.feed(anon.request('/')[1])
            assert {'/#features', '/#characters', '/#plans', '/#auth'} <= home_links.hrefs
            demo_links = AnchorLinks()
            demo_links.feed(anon.request('/demo.php')[1])
            assert {'/', '/#auth', '/?register=1'} <= demo_links.hrefs
            print('PASS clean homepage section navigation and demo return links')
            character = scalar('SELECT id FROM characters WHERE is_premium = 0 LIMIT 1')
            def login(client, email='one@example.test', password='password123'):
                client.request('/index.php?switch=1')
                return client.request('/index.php', dict(login='1', email=email, password=password))
            accounts = []
            for name in ('one', 'two'):
                client = Client(base)
                client.request('/index.php')
                data = dict(register='1', child_name=name, child_age='8', parent_name='Parent',
                            parent_phone='0599123456', email=name + '@example.test', password='password123',
                            confirm_password='password123', character_1=character, parent_consent='1')
                for token in (None, 'wrong'):
                    bad = dict(data)
                    if token:
                        bad['csrf_token'] = token
                    assert client.request('/index.php', bad, csrf=False)[0] == 403
                    assert scalar('SELECT COUNT(*) FROM children WHERE email = ?', [data['email']]) == 0
                assert client.request('/index.php', data)[0] == 302
                accounts.append(client)
            owner, other = accounts
            child = scalar('SELECT id FROM children WHERE email = ?', ['one@example.test'])
            before = sql('SELECT * FROM children ORDER BY id')
            php('scripts/migrate.php')
            assert sql('SELECT * FROM children ORDER BY id') == before
            print('PASS migrations preserve registered data; registration and CSRF')
            second = Client(base)
            assert login(second)[0] == 302
            owner.request('/tasks.php')
            task = json.loads(scalar('SELECT task_pool_ids FROM daily_progress WHERE child_id = ?', [child]))[0]
            points = scalar('SELECT points FROM children WHERE id = ?', [child])
            for client in (owner, second):
                assert client.request('/api/complete-task.php', {'task_id': task, 'csrf_token': 'bad'})[0] == 403
            assert scalar('SELECT points FROM children WHERE id = ?', [child]) == points
            def complete(client):
                return client.request('/api/complete-task.php', {'task_id': task})
            with concurrent.futures.ThreadPoolExecutor() as pool:
                results = list(pool.map(complete, (owner, second)))
            assert sum(bool(json.loads(body).get('ok')) for _, body in results) == 1, results
            assert scalar('SELECT points FROM children WHERE id = ?', [child]) == points + scalar('SELECT points FROM tasks WHERE id = ?', [task])
            sql('UPDATE daily_progress SET games_played = 3 WHERE child_id = ?', [child])
            points = scalar('SELECT points FROM children WHERE id = ?', [child])
            rings = scalar('SELECT ring_days FROM children WHERE id = ?', [child])
            with concurrent.futures.ThreadPoolExecutor() as pool:
                results = list(pool.map(lambda c: c.request('/story.php', {'generate_story': '1'}), (owner, second)))
            assert all(code in (200, 302) for code, _ in results), results
            assert scalar('SELECT COUNT(*) FROM daily_stories WHERE child_id = ?', [child]) == 1
            assert scalar('SELECT points FROM children WHERE id = ?', [child]) == points + 10
            assert scalar('SELECT ring_days FROM children WHERE id = ?', [child]) == rings + 1
            print('PASS concurrent duplicate daily task/story from independent sessions')
            method_failures = []
            for path in ('complete-task', 'swap-companion', 'play-game', 'assess-answer', 'log-wa', 'save-drawing'):
                if owner.request('/api/' + path + '.php')[0] != 405:
                    method_failures.append(path)
            plans = sql('SELECT id FROM subscription_plans ORDER BY price_ils')
            for plan in plans[:2]:
                assert owner.request('/subscriptions.php', {'request_plan_id': plan['id']})[0] == 302
                assert scalar('SELECT COUNT(*) FROM subscriptions WHERE child_id = ?', [child]) == 1
                assert scalar('SELECT plan_id FROM subscriptions WHERE child_id = ?', [child]) == plan['id']
            sql("UPDATE children SET trial_ends_at = '2000-01-01 00:00:00' WHERE id = ?", [child])
            sql('DELETE FROM subscriptions WHERE child_id = ?', [child])
            assert owner.request('/api/complete-task.php', {'task_id': task})[0] == 403
            print('PASS subscription replacement/access expiry')
            for page, games, story in (('dashboard', False, False), ('profile', False, True), ('games', True, False), ('story', False, True)):
                code, body = owner.request('/' + page + '.php')
                assert code == 200, page
                assert bool(re.search(r'<script[^>]+src="[^"]*/games-engine.js', body)) == games, page
                assert bool(re.search(r'<script[^>]+src="[^"]*/story-player.js', body)) == story, page
            for path in ('/config/config.php', '/database/schema.sql', '/includes/functions.php', '/scripts/migrate.php', '/storage/kidora.sqlite', '/uploads/photos/x.png', '/.git/config'):
                assert anon.request(path)[0] == 403, path
            debug_status = anon.request('/test-cookie.php')[0]
            assert not (ROOT / 'test-cookie.php').exists()
            print('PASS script scoping and internal path protection')
            # Preserve the old session and remember cookie before consuming a fixture reset.
            remember = owner.cookie('kidora_remember')
            assert remember
            # Logging in another session rotates the one stored remember token; use its current token.
            remember = second.cookie('kidora_remember')
            assert scalar('SELECT remember_token FROM children WHERE id = ?', [child]) == hashlib.sha256(remember.encode()).hexdigest()
            token = 'a' * 64
            sql('INSERT INTO password_resets (child_id, token_hash, expires_at) VALUES (?,?,?)',
                [child, hashlib.sha256(token.encode()).hexdigest(), '2099-01-01 00:00:00'])
            reset = Client(base)
            reset.request('/reset-password.php?token=' + token)
            payload = dict(set_password='1', token=token, password='changed123', confirm_password='changed123')
            assert reset.request('/reset-password.php', payload)[0] == 302
            version = scalar('SELECT auth_version FROM children WHERE id = ?', [child])
            reset.request('/reset-password.php', payload)
            assert scalar('SELECT auth_version FROM children WHERE id = ?', [child]) == version
            assert scalar('SELECT remember_token FROM children WHERE id = ?', [child]) is None
            for client in (owner, second):
                assert client.request('/dashboard.php')[0] == 302
            stale = Client(base)
            stale.jar.set_cookie(http.cookiejar.Cookie(0, 'kidora_remember', remember, None, False, '127.0.0.1', False, False, '/', True, False, None, True, None, None, {}))
            assert stale.request('/dashboard.php')[0] == 302
            assert login(Client(base), password='changed123')[0] == 302
            print('PASS hashed remember token, single-use reset, session/token revocation')
            admin = Client(base)
            admin.request('/admin/login.php')
            old_id = admin.cookie('PHPSESSID')
            assert admin.request('/admin/login.php', {'email': 'admin@kidora.com', 'password': 'admin123'})[0] == 302
            assert old_id != admin.cookie('PHPSESSID')
            for path, email, flag in (('/admin/login.php', 'admin@kidora.com', {}), ('/index.php', 'two@example.test', {'login': '1'})):
                sql('DELETE FROM auth_attempts')
                attacker = Client(base)
                attacker.request(path)
                for _ in range(10):
                    attacker.request(path, dict(flag, email=email, password='incorrect'))
                assert attacker.request(path, dict(flag, email=email, password='admin123' if not flag else 'password123'))[0] == 429
            print('PASS admin session rotation and child/admin shared login throttles')
            sql('DELETE FROM auth_attempts')
            # All three ownership references must be enforced by the same private endpoint.
            media = Path(temp) / 'uploads/photos/regression.png'
            media.parent.mkdir(parents=True, exist_ok=True)
            media.write_bytes(base64.b64decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aZWQAAAAASUVORK5CYII='))
            rel = 'uploads/photos/regression.png'
            for table, column in (('children', 'photo_path'), ('daily_stories', 'photo_path'), ('drawings', 'image_path')):
                sql('UPDATE children SET photo_path = NULL WHERE id = ?', [child])
                sql('UPDATE daily_stories SET photo_path = NULL WHERE child_id = ?', [child])
                sql('DELETE FROM drawings WHERE child_id = ?', [child])
                if table == 'drawings':
                    sql('INSERT INTO drawings (child_id, image_path) VALUES (?,?)', [child, rel])
                else:
                    sql(f'UPDATE {table} SET {column} = ? WHERE ' + ('id' if table == 'children' else 'child_id') + ' = ?', [rel, child])
                fresh = Client(base)
                assert login(fresh, password='changed123')[0] == 302
                path = '/media.php?path=' + urllib.parse.quote(rel)
                assert fresh.request(path)[0] == 200, table
                assert other.request(path)[0] in (403, 404), table
                assert anon.request(path)[0] in (401, 403, 404), table
            print('PASS private media ownership across child/story/drawing and anonymous denial')
            expired = media.with_name('expired.png')
            expired.write_bytes(media.read_bytes())
            old = time.time() - 366 * 86400
            os.utime(expired, (old, old))
            expired_rel = 'uploads/photos/expired.png'
            sql('UPDATE children SET photo_path = ? WHERE id = ?', [expired_rel, child])
            assert fresh.request('/media.php?path=' + urllib.parse.quote(expired_rel))[0] == 404
            php('scripts/prune-media.php')
            assert expired.exists(), 'Retention dry run must not delete'
            php('scripts/prune-media.php', '--apply')
            assert not expired.exists() and media.exists()
            assert scalar('SELECT photo_path FROM children WHERE id = ?', [child]) is None
            assert fresh.request('/profile.php', {'delete_account': '1', 'current_password': 'wrong'})[0] == 302
            assert scalar('SELECT COUNT(*) FROM children WHERE id = ?', [child]) == 1
            assert fresh.request('/profile.php', {'delete_account': '1', 'current_password': payload['password']})[0] == 302
            assert not media.exists()
            for table in ('children', 'daily_stories', 'daily_progress', 'subscriptions', 'drawings', 'password_resets', 'quiz_history', 'wa_log', 'grand_stories'):
                key = 'id' if table == 'children' else 'child_id'
                assert scalar(f'SELECT COUNT(*) FROM {table} WHERE {key} = ?', [child]) == 0, table
            assert scalar('SELECT COUNT(*) FROM children WHERE email = ?', ['two@example.test']) == 1
            assert other.request('/dashboard.php')[0] == 200
            print('PASS retention dry-run/apply and password-confirmed account/media deletion with other-account control')
            assert debug_status == 404, f'Debug endpoint returned {debug_status}, expected 404'
            assert not method_failures, 'GET mutations must return 405: ' + ', '.join(method_failures)
        except Exception:
            log.flush()
            log.seek(0)
            print(log.read()[-12000:])
            raise
        finally:
            os.killpg(server.pid, signal.SIGTERM)
            server.wait(timeout=10)
            log.close()


if __name__ == '__main__':
    main()
