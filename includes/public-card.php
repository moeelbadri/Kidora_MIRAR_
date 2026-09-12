<?php
/* بطاقة زجاجية للصفحات العامة الصغيرة (استرجاع كلمة المرور وما يشبهها).
   تُطابق ألوان بطاقة الدخول في index.php حتى تبدو من نفس العائلة. */
?>
<style>
  .pc-page{position:relative;z-index:2;color:#f1f5f9;min-height:calc(100vh - 72px);display:grid;place-items:start center;padding:60px 16px 90px}
  .pc-card{width:min(520px,100%);padding:32px 28px;border:1px solid rgba(255,255,255,.16);border-radius:30px;background:rgba(255,255,255,.08);backdrop-filter:blur(18px);box-shadow:0 26px 70px rgba(0,0,0,.25)}
  .pc-icon{font-size:52px;text-align:center;line-height:1}
  .pc-card h1{margin:12px 0 6px;text-align:center;font-family:var(--font-display);font-size:30px;color:#fff}
  .pc-lead{margin:0 0 22px;text-align:center;color:#d9d0ff;font-size:15px;line-height:1.9}
  .pc-field{margin-bottom:15px}
  .pc-field label{display:block;margin-bottom:6px;color:#f1f5f9;font-size:13px;font-weight:800}
  .pc-field input{width:100%;min-height:47px;border:1px solid rgba(255,255,255,.15);border-radius:13px;padding:10px 13px;color:#fff;background:rgba(0,0,0,.24);font:inherit}
  .pc-field input:focus{outline:2px solid #ffc93c;outline-offset:1px}
  .pc-btn{display:flex;align-items:center;justify-content:center;gap:9px;width:100%;min-height:52px;padding:12px 25px;border:0;border-radius:999px;font:inherit;font-size:16px;font-weight:900;color:#241645;background:linear-gradient(135deg,#ffe99a,#ffc93c);box-shadow:0 12px 28px rgba(255,201,60,.28);cursor:pointer;transition:transform .2s}
  .pc-btn:hover{transform:translateY(-3px)}
  .pc-msg{padding:12px 14px;border-radius:14px;margin-bottom:16px;font-weight:700;line-height:1.8;font-size:14px}
  .pc-msg.ok{background:rgba(46,196,182,.18);border:1px solid rgba(46,196,182,.5);color:#d9fff9}
  .pc-msg.err{background:rgba(229,72,77,.18);border:1px solid rgba(229,72,77,.5);color:#ffd9db}
  .pc-links{margin:20px 0 0;text-align:center;color:#b9abd4;font-size:13px}
  .pc-links a{color:#ffe99a;font-weight:900}
</style>
