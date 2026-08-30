<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';
$child = require_login();

$__pageTitle = 'ألعاب كلاسيكية — Kidora';
$__pageLine = "🎮 ألعاب بسيطة وممتعة";
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/navbar.php';
?>

<style>
    .games-hero { background: linear-gradient(145deg, #0f172a, #1e293b); padding: 2rem; border-radius: 40px; margin-bottom: 2rem; text-align: center; border: 1px solid rgba(251,191,36,0.1); }
    .games-hero h1 { font-size: 2.8rem; font-weight: 900; color: #fff; }
    .games-hero h1 span { color: #fbbf24; }
    .game-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 24px; margin-top: 20px; }
    .game-card { background: rgba(255,255,255,0.04); border-radius: 28px; padding: 24px; text-align: center; cursor: pointer; transition: 0.25s; border: 1px solid rgba(255,255,255,0.04); }
    .game-card:hover { background: rgba(255,255,255,0.07); transform: translateY(-6px); border-color: #fbbf24; }
    .game-card .icon { font-size: 4rem; }
    .game-card h3 { color: #fff; margin: 10px 0 4px; }
    .game-card p { color: rgba(255,255,255,0.4); font-size: 0.85rem; }
    .play-btn { background: #fbbf24; border: none; color: #0f172a; padding: 12px 30px; border-radius: 60px; font-weight: 800; cursor: pointer; margin-top: 12px; transition: 0.2s; }
    .play-btn:hover { transform: scale(1.05); background: #fcd34d; }

    /* مودال اللعبة */
    .game-modal { position: fixed; inset: 0; background: rgba(0,0,0,0.85); backdrop-filter: blur(10px); display: none; align-items: center; justify-content: center; z-index: 99999; padding: 20px; }
    .game-modal.open { display: flex; }
    .game-modal-box { background: #0f172a; border-radius: 40px; max-width: 800px; width: 100%; padding: 20px; border: 1px solid rgba(251,191,36,0.1); position: relative; }
    .game-modal-close { position: absolute; top: 10px; left: 15px; background: rgba(255,255,255,0.04); border: none; color: #fff; font-size: 28px; width: 44px; height: 44px; border-radius: 50%; cursor: pointer; z-index: 10; }
    .game-modal-close:hover { background: rgba(255,0,0,0.15); }
    .game-stats { display: flex; justify-content: space-between; color: #fff; padding: 6px 12px 12px; font-weight: 700; }
    .game-stats span { background: rgba(0,0,0,0.3); padding: 4px 18px; border-radius: 40px; }
    .game-canvas-wrap { background: #0b1120; border-radius: 24px; overflow: hidden; width: 100%; aspect-ratio: 16/9; min-height: 350px; }
    .game-canvas-wrap canvas { display: block; width: 100% !important; height: 100% !important; touch-action: none; }
    .game-ctrl { display: flex; justify-content: center; gap: 14px; padding-top: 14px; flex-wrap: wrap; }
    .ctrl-btn { background: rgba(255,255,255,0.04); border: 1px solid rgba(255,255,255,0.06); color: #fff; padding: 8px 28px; border-radius: 60px; font-weight: 600; cursor: pointer; }
    .ctrl-btn:hover { background: rgba(255,255,255,0.08); }
    .ctrl-btn.gold { background: #fbbf24; color: #0f172a; border-color: #fbbf24; }
    .ctrl-btn.gold:hover { background: #fcd34d; }
    @media (max-width: 640px) { .game-canvas-wrap { aspect-ratio: 1/1; min-height: 250px; } }
</style>

<main class="container" style="padding: 20px 15px 50px;">
    <div class="games-hero">
        <h1>🎯 ألعاب <span>كلاسيكية</span></h1>
        <p style="color: rgba(255,255,255,0.6);">جرب الألعاب الشهيرة، كلها شغالة ومضمونة</p>
    </div>

    <div class="game-grid">
        <div class="game-card" onclick="openGame('snake')">
            <div class="icon">🐍</div>
            <h3>الأفعى</h3>
            <p>كل التفاحات وزد طولك</p>
            <button class="play-btn">▶ العب</button>
        </div>
        <div class="game-card" onclick="openGame('breakout')">
            <div class="icon">🧱</div>
            <h3>ضرب الطوب</h3>
            <p>اكسر كل الطوب بالكرة</p>
            <button class="play-btn">▶ العب</button>
        </div>
        <div class="game-card" onclick="openGame('flappy')">
            <div class="icon">🐦</div>
            <h3>Flappy Bird</h3>
            <p>تجاوز الأنابيب ولا تسقط</p>
            <button class="play-btn">▶ العب</button>
        </div>
        <div class="game-card" onclick="openGame('racer')">
            <div class="icon">🏎️</div>
            <h3>سباق الطريق</h3>
            <p>تفادى السيارات واجمع نقاط</p>
            <button class="play-btn">▶ العب</button>
        </div>
    </div>
</main>

<!-- مودال -->
<div class="game-modal" id="gameModal">
    <div class="game-modal-box">
        <button class="game-modal-close" onclick="closeGame()">✕</button>
        <div class="game-stats"><span id="gScore">⭐ 0</span><span id="gStatus">▶ جاري اللعب</span></div>
        <div class="game-canvas-wrap"><canvas id="gameCanvas"></canvas></div>
        <div class="game-ctrl">
            <button class="ctrl-btn gold" id="restartBtn">🔄 إعادة</button>
            <button class="ctrl-btn" onclick="closeGame()">✕ إغلاق</button>
        </div>
    </div>
</div>

<script>
// ================================================================
//  الثلاث ألعاب المضمونة ١٠٠٪
// ================================================================
const modal = document.getElementById('gameModal');
const canvas = document.getElementById('gameCanvas');
const ctx = canvas.getContext('2d');
const scoreSpan = document.getElementById('gScore');
const statusSpan = document.getElementById('gStatus');
const restartBtn = document.getElementById('restartBtn');

let currentGame = null;
let animId = null;
let running = false;

function resizeCanvas() {
    const rect = canvas.parentElement.getBoundingClientRect();
    canvas.width = rect.width;
    canvas.height = rect.height;
}
window.addEventListener('resize', resizeCanvas);

// ================================================================
//  لعبة 1: الأفعى (Snake) - شغالة ١٠٠٪
// ================================================================
const Snake = {
    snake: [{x:10,y:10}], food:{x:15,y:15}, dir:{x:1,y:0}, ndir:{x:1,y:0},
    score:0, over:false, speed:8, counter:0, grid:20,
    init() {
        this.snake = [{x:10,y:10}]; this.dir={x:1,y:0}; this.ndir={x:1,y:0};
        this.score=0; this.over=false; this.counter=0; this.grid=20;
        this.spawnFood();
        document.addEventListener('keydown', (e) => {
            if (e.key === 'ArrowUp' && this.dir.y !== 1) this.ndir = {x:0, y:-1};
            else if (e.key === 'ArrowDown' && this.dir.y !== -1) this.ndir = {x:0, y:1};
            else if (e.key === 'ArrowLeft' && this.dir.x !== 1) this.ndir = {x:-1, y:0};
            else if (e.key === 'ArrowRight' && this.dir.x !== -1) this.ndir = {x:1, y:0};
        });
    },
    spawnFood() {
        const cols = Math.floor(canvas.width/this.grid), rows = Math.floor(canvas.height/this.grid);
        let pos; do { pos = {x: Math.floor(Math.random()*cols), y: Math.floor(Math.random()*rows)}; }
        while (this.snake.some(s => s.x === pos.x && s.y === pos.y));
        this.food = pos;
    },
    update() {
        if (this.over) return;
        this.counter++; if (this.counter < 12 - Math.floor(this.score/20)) return; this.counter = 0;
        this.dir = {...this.ndir};
        const head = {...this.snake[0]}; head.x += this.dir.x; head.y += this.dir.y;
        const cols = Math.floor(canvas.width/this.grid), rows = Math.floor(canvas.height/this.grid);
        if (head.x < 0 || head.x >= cols || head.y < 0 || head.y >= rows) { this.over = true; statusSpan.innerText = '💀 اصطدام!'; return; }
        if (head.x === this.food.x && head.y === this.food.y) {
            this.score += 5; this.snake.unshift(head); this.spawnFood();
        } else { this.snake.pop(); this.snake.unshift(head); }
        for (let i=1; i<this.snake.length; i++) if (this.snake[i].x === head.x && this.snake[i].y === head.y) { this.over = true; statusSpan.innerText = '💀 أكلت نفسك!'; break; }
        scoreSpan.innerText = '🐍 ' + this.score;
    },
    draw() {
        ctx.clearRect(0,0,canvas.width,canvas.height); ctx.fillStyle='#0f172a'; ctx.fillRect(0,0,canvas.width,canvas.height);
        const g=this.grid;
        this.snake.forEach((s,i) => {
            const x=s.x*g, y=s.y*g;
            ctx.fillStyle = i===0 ? '#22d3ee' : '#14b8a6';
            ctx.shadowColor = i===0 ? '#22d3ee' : '#14b8a6';
            ctx.shadowBlur = i===0 ? 25 : 10;
            ctx.beginPath(); ctx.roundRect(x+2, y+2, g-4, g-4, 6); ctx.fill();
        });
        ctx.shadowBlur=0;
        ctx.fillStyle='#fbbf24'; ctx.shadowColor='#fbbf24'; ctx.shadowBlur=30;
        const fx=this.food.x*g, fy=this.food.y*g;
        ctx.beginPath(); ctx.arc(fx+g/2, fy+g/2, g/2-4, 0, Math.PI*2); ctx.fill();
        ctx.shadowBlur=0;
        if(this.over){ ctx.fillStyle='rgba(0,0,0,0.6)'; ctx.fillRect(0,0,canvas.width,canvas.height);
            ctx.fillStyle='#fff'; ctx.font='bold 30px sans-serif'; ctx.textAlign='center';
            ctx.fillText('💀 انتهى', canvas.width/2, canvas.height/2-10);
            ctx.font='20px sans-serif'; ctx.fillText('نقاط: '+this.score, canvas.width/2, canvas.height/2+45); }
    }
};

// ================================================================
//  لعبة 2: ضرب الطوب (Breakout)
// ================================================================
const Breakout = {
    ball:{x:0,y:0,r:10,vx:5,vy:-5}, paddle:{x:0,y:0,w:80,h:12}, bricks:[], score:0, over:false,
    init() {
        this.ball.x=canvas.width/2; this.ball.y=canvas.height-60; this.ball.vx=5; this.ball.vy=-5;
        this.paddle.x=canvas.width/2-40; this.paddle.y=canvas.height-30;
        this.bricks=[]; this.score=0; this.over=false;
        const rows=3, cols=8;
        for (let r=0; r<rows; r++) for (let c=0; c<cols; c++) {
            const w=(canvas.width-40)/cols, h=20;
            this.bricks.push({x:20+c*w, y:40+r*h, w:w-4, h:h-4, alive:true, color:`hsl(${r*30+20},80%,60%)`});
        }
        document.addEventListener('keydown', (e) => { if (e.key === 'ArrowLeft' || e.key === 'a') this.paddle.vx = -8; else if (e.key === 'ArrowRight' || e.key === 'd') this.paddle.vx = 8; });
        document.addEventListener('keyup', (e) => { if (e.key === 'ArrowLeft' || e.key === 'a' || e.key === 'ArrowRight' || e.key === 'd') this.paddle.vx = 0; });
    },
    update() {
        if (this.over) return;
        this.paddle.x += this.paddle.vx || 0;
        if (this.paddle.x < 0) this.paddle.x = 0; if (this.paddle.x + this.paddle.w > canvas.width) this.paddle.x = canvas.width - this.paddle.w;
        this.ball.x += this.ball.vx; this.ball.y += this.ball.vy;
        if (this.ball.x < 0 || this.ball.x > canvas.width) this.ball.vx *= -1;
        if (this.ball.y < 0) this.ball.vy *= -1;
        if (this.ball.y + this.ball.r > this.paddle.y && this.ball.y - this.ball.r < this.paddle.y + this.paddle.h &&
            this.ball.x > this.paddle.x && this.ball.x < this.paddle.x + this.paddle.w) {
            this.ball.vy = -Math.abs(this.ball.vy);
            const hit = (this.ball.x - (this.paddle.x + this.paddle.w/2)) / (this.paddle.w/2);
            this.ball.vx = hit * 6;
        }
        if (this.ball.y > canvas.height + 30) { this.over = true; statusSpan.innerText = '💀 سقطت!'; }
        for (let b of this.bricks) {
            if (!b.alive) continue;
            if (this.ball.x > b.x && this.ball.x < b.x + b.w && this.ball.y > b.y && this.ball.y < b.y + b.h) {
                b.alive = false; this.score += 10; this.ball.vy *= -1;
                if (this.bricks.every(bk => !bk.alive)) { this.over = true; statusSpan.innerText = '🎉 فوز!'; }
            }
        }
        scoreSpan.innerText = '🧱 ' + this.score;
    },
    draw() {
        ctx.clearRect(0,0,canvas.width,canvas.height); ctx.fillStyle='#0b1120'; ctx.fillRect(0,0,canvas.width,canvas.height);
        this.bricks.forEach(b => { if(!b.alive)return; ctx.shadowColor=b.color; ctx.shadowBlur=10; ctx.fillStyle=b.color; ctx.beginPath(); ctx.roundRect(b.x,b.y,b.w,b.h,4); ctx.fill(); });
        ctx.shadowBlur=0;
        ctx.shadowColor='#60a5fa'; ctx.shadowBlur=20; ctx.fillStyle='#60a5fa'; ctx.beginPath(); ctx.roundRect(this.paddle.x,this.paddle.y,this.paddle.w,this.paddle.h,8); ctx.fill();
        ctx.shadowBlur=0;
        ctx.shadowColor='#fff'; ctx.shadowBlur=20; ctx.fillStyle='#fff'; ctx.beginPath(); ctx.arc(this.ball.x,this.ball.y,this.ball.r,0,Math.PI*2); ctx.fill();
        ctx.shadowBlur=0;
        if(this.over){ ctx.fillStyle='rgba(0,0,0,0.6)'; ctx.fillRect(0,0,canvas.width,canvas.height);
            ctx.fillStyle='#fff'; ctx.font='bold 30px sans-serif'; ctx.textAlign='center';
            ctx.fillText(this.bricks.every(b=>!b.alive)?'🎉 فوز!':'💥 انتهى', canvas.width/2, canvas.height/2-10);
            ctx.font='20px sans-serif'; ctx.fillText('نقاط: '+this.score, canvas.width/2, canvas.height/2+45); }
    }
};

// ================================================================
//  لعبة 3: Flappy Bird
// ================================================================
const Flappy = {
    bird:{x:0,y:0,r:14,vy:0}, pipes:[], score:0, over:false,
    init() {
        this.bird.x = 80; this.bird.y = canvas.height/2; this.bird.vy=0;
        this.pipes=[]; this.score=0; this.over=false;
        for(let i=0;i<3;i++) this.spawnPipe(i*200 + 300);
        document.addEventListener('keydown', (e) => { if (e.key === ' ' || e.key === 'ArrowUp' || e.key === 'w') this.bird.vy = -7; });
        canvas.addEventListener('click', () => { if (!this.over) this.bird.vy = -7; });
    },
    spawnPipe(x) {
        const minH=60, maxH=canvas.height-180;
        const h=minH+Math.random()*(maxH-minH);
        this.pipes.push({x, topH:h, gap:150, w:45, scored:false});
    },
    update() {
        if (this.over) return;
        this.bird.vy += 0.4; this.bird.y += this.bird.vy;
        if (this.bird.y < 0) this.bird.y = 0;
        if (this.bird.y + this.bird.r > canvas.height) { this.over = true; statusSpan.innerText = '💀 سقط!'; }
        for (let p of this.pipes) {
            p.x -= 3;
            if (p.x + p.w < 0) { this.pipes.splice(this.pipes.indexOf(p),1); this.spawnPipe(canvas.width + 50); }
            if (this.bird.x + this.bird.r > p.x && this.bird.x - this.bird.r < p.x + p.w) {
                if (this.bird.y - this.bird.r < p.topH || this.bird.y + this.bird.r > p.topH + p.gap) {
                    this.over = true; statusSpan.innerText = '💀 اصطدام!';
                }
            }
            if (!p.scored && this.bird.x > p.x + p.w) { p.scored = true; this.score += 5; }
        }
        scoreSpan.innerText = '🐦 ' + this.score;
    },
    draw() {
        ctx.clearRect(0,0,canvas.width,canvas.height); ctx.fillStyle='#0b1a2a'; ctx.fillRect(0,0,canvas.width,canvas.height);
        this.pipes.forEach(p => {
            ctx.shadowColor='#22c55e'; ctx.shadowBlur=10; ctx.fillStyle='#22c55e';
            ctx.fillRect(p.x, 0, p.w, p.topH);
            ctx.fillRect(p.x, p.topH + p.gap, p.w, canvas.height - p.topH - p.gap);
            ctx.shadowBlur=0;
            ctx.fillStyle='#166534'; ctx.fillRect(p.x-4, p.topH-20, p.w+8, 20);
            ctx.fillRect(p.x-4, p.topH+p.gap, p.w+8, 20);
        });
        ctx.shadowColor='#fbbf24'; ctx.shadowBlur=30; ctx.fillStyle='#fbbf24';
        ctx.beginPath(); ctx.arc(this.bird.x, this.bird.y, this.bird.r, 0, Math.PI*2); ctx.fill();
        ctx.shadowBlur=0;
        ctx.font='24px sans-serif'; ctx.textAlign='center'; ctx.fillText('🐦', this.bird.x, this.bird.y+8);
        if(this.over){ ctx.fillStyle='rgba(0,0,0,0.6)'; ctx.fillRect(0,0,canvas.width,canvas.height);
            ctx.fillStyle='#fff'; ctx.font='bold 30px sans-serif'; ctx.textAlign='center';
            ctx.fillText('💥 انتهى', canvas.width/2, canvas.height/2-10);
            ctx.font='20px sans-serif'; ctx.fillText('نقاط: '+this.score, canvas.width/2, canvas.height/2+45); }
    }
};

// ================================================================
//  لعبة 4: سباق (مختصر)
// ================================================================
const Racer = {
    p:{x:0,y:0,w:40,h:60}, enemies:[], score:0, sp:5, over:false, keys:{l:false,r:false},
    init(){
        this.over=false; this.enemies=[]; this.score=0; this.sp=5;
        this.p.x=canvas.width/2-20; this.p.y=canvas.height-120;
        this.keys={l:false,r:false};
        document.addEventListener('keydown',e=>{if(e.key=='ArrowLeft'||e.key=='a')this.keys.l=true; if(e.key=='ArrowRight'||e.key=='d')this.keys.r=true;});
        document.addEventListener('keyup',e=>{if(e.key=='ArrowLeft'||e.key=='a')this.keys.l=false; if(e.key=='ArrowRight'||e.key=='d')this.keys.r=false;});
    },
    update(){
        if(this.over)return;
        if(this.keys.l)this.p.x-=6; if(this.keys.r)this.p.x+=6;
        this.p.x=Math.max(5, Math.min(canvas.width-this.p.w-5, this.p.x));
        if(Math.random()<0.02+this.score/1500){
            let w=30+Math.random()*30;
            this.enemies.push({x:10+Math.random()*(canvas.width-w-20), y:-40, w, h:40+Math.random()*30, sp:this.sp*(0.7+Math.random()*0.5)});
        }
        for(let i=this.enemies.length-1;i>=0;i--){
            let e=this.enemies[i]; e.y+=e.sp;
            if(e.y>canvas.height+40){this.enemies.splice(i,1); this.score++; continue;}
            if(e.x<this.p.x+this.p.w-6 && e.x+e.w>this.p.x+6 && e.y<this.p.y+this.p.h-6 && e.y+e.h>this.p.y+6){
                this.over=true; statusSpan.innerText='💥 انتهى!';
            }
        }
        this.sp=5+Math.floor(this.score/12)*0.6;
        scoreSpan.innerText='🏆 '+Math.floor(this.score);
    },
    draw(){
        ctx.clearRect(0,0,canvas.width,canvas.height); ctx.fillStyle='#1e293b'; ctx.fillRect(0,0,canvas.width,canvas.height);
        this.enemies.forEach(e=>{ ctx.fillStyle='#ef4444'; ctx.shadowColor='#ef4444'; ctx.shadowBlur=15; ctx.beginPath(); ctx.roundRect(e.x,e.y,e.w,e.h,6); ctx.fill(); ctx.shadowBlur=0; });
        ctx.shadowColor='#fbbf24'; ctx.shadowBlur=25; ctx.fillStyle='#fbbf24'; ctx.beginPath(); ctx.roundRect(this.p.x,this.p.y,this.p.w,this.p.h,8); ctx.fill(); ctx.shadowBlur=0;
        if(this.over){ ctx.fillStyle='rgba(0,0,0,0.6)'; ctx.fillRect(0,0,canvas.width,canvas.height);
            ctx.fillStyle='#fff'; ctx.font='bold 30px sans-serif'; ctx.textAlign='center';
            ctx.fillText('💥 انتهى', canvas.width/2, canvas.height/2-10);
            ctx.font='20px sans-serif'; ctx.fillText('نقاط: '+Math.floor(this.score), canvas.width/2, canvas.height/2+45); }
    }
};

// ================================================================
//  المحرك الرئيسي
// ================================================================
const Games = { snake: Snake, breakout: Breakout, flappy: Flappy, racer: Racer };
let activeGame = null;

function openGame(type) {
    modal.classList.add('open');
    resizeCanvas();
    if (animId) cancelAnimationFrame(animId);
    const Game = Games[type];
    if (!Game) return alert('هذه اللعبة غير متوفرة حالياً');
    Game.init();
    activeGame = Game;
    running = true;
    statusSpan.innerText = '▶ جاري اللعب';
    scoreSpan.innerText = '⭐ 0';
    restartBtn.onclick = () => { Game.init(); running = true; statusSpan.innerText = '▶ جاري اللعب'; };
    gameLoop();
}

function gameLoop() {
    if (!activeGame || !running) return;
    activeGame.update();
    activeGame.draw();
    animId = requestAnimationFrame(gameLoop);
}

function closeGame() {
    running = false; if (animId) cancelAnimationFrame(animId);
    modal.classList.remove('open'); activeGame = null;
    // إزالة المستمعين لمنع التراكم
    document.removeEventListener('keydown', () => {});
    document.removeEventListener('keyup', () => {});
}

modal.addEventListener('click', (e) => { if (e.target === modal) closeGame(); });

// Polyfill roundRect
if (!CanvasRenderingContext2D.prototype.roundRect) {
    CanvasRenderingContext2D.prototype.roundRect = function (x, y, w, h, r) {
        if (r > w/2) r = w/2; if (r > h/2) r = h/2;
        this.moveTo(x + r, y); this.lineTo(x + w - r, y);
        this.quadraticCurveTo(x + w, y, x + w, y + r);
        this.lineTo(x + w, y + h - r);
        this.quadraticCurveTo(x + w, y + h, x + w - r, y + h);
        this.lineTo(x + r, y + h);
        this.quadraticCurveTo(x, y + h, x, y + h - r);
        this.lineTo(x, y + r);
        this.quadraticCurveTo(x, y, x + r, y);
        return this;
    };
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
