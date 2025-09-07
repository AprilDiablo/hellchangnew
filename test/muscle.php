<!doctype html>
<html>
<head>
<meta charset="utf-8" />
<style>
  .container {
    position: relative;
    width: 768px;   /* 원본 이미지 크기 맞추기 */
    height: 1152px;
  }
  .container img {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: auto;
  }
  .overlay {
    opacity: 1;
    transition: opacity 0.3s;
  }
  .off {
    opacity: 0;
  }
</style>
</head>
<body>

<div>
  <button onclick="toggle('upper')">상부 토글</button>
  <button onclick="toggle('middle')">중부 토글</button>
  <button onclick="toggle('lower')">하부 토글</button>
</div>

<div class="container">
  <!-- 원본 이미지 -->
  <img src="m.png" alt="원본" />

  <!-- 오버레이 (투명 PNG) -->
  <img id="upper" class="overlay" src="trapezius_upper.png" alt="상부 승모근" />
  <img id="middle" class="overlay" src="trapezius_middle.png" alt="중부 승모근" />
  <img id="lower" class="overlay" src="trapezius_lower.png" alt="하부 승모근" />
</div>

<script>
function toggle(part) {
  const el = document.getElementById(part);
  el.classList.toggle('off');
}
</script>

</body>
</html>
