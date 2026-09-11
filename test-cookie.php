<?php
setcookie('test1', 'value1', time() + 3600, '/');
setcookie('test2', 'value2', time() + 3600, '/', '', false, true);
echo "Test cookies set. Check F12 → Application → Cookies";
