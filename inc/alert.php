<?php if (isset($_GET['success']) || isset($_GET['error'])): ?>
  <div class="alert-container" style="margin-bottom:20px;">
    <?php if (isset($_GET['success'])): ?>
      <div class="alert success">
        <i class="bi bi-check-circle-fill"></i>
        <?php echo htmlspecialchars($_GET['success']); ?>
        <button class="close" onclick="this.parentElement.style.display='none';">&times;</button>
      </div>
    <?php endif; ?>

    <?php if (isset($_GET['error'])): ?>
      <div class="alert error">
        <i class="bi bi-exclamation-triangle-fill"></i>
        <?php echo htmlspecialchars($_GET['error']); ?>
        <button class="close" onclick="this.parentElement.style.display='none';">&times;</button>
      </div>
    <?php endif; ?>
  </div>
<?php endif; ?>
