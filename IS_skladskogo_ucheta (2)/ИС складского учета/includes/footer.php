<?php if (isLoggedIn()): ?>
            </div> <!-- content-body -->
        </div> <!-- main-content -->
    </div> <!-- app-layout -->
    <?php else: ?>
    <footer class="auth-footer">
        <div class="container">
            <p>&copy; <?= date('Y') ?> <?= APP_NAME ?>. Все права защищены.</p>
            <div class="social-links">
                <a href="#" title="Facebook"><img src="<?= BASE_URL ?>img/facebook.png" alt="Facebook"></a>
                <a href="#" title="VK"><img src="<?= BASE_URL ?>img/vk.png" alt="VK"></a>
                <a href="#" title="Instagram"><img src="<?= BASE_URL ?>img/instagram.png" alt="Instagram"></a>
            </div>
        </div>
    </footer>
    <?php endif; ?>
    
            <!-- Bootstrap JavaScript -->
            <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
            <script src="<?= BASE_URL ?>js/script.js"></script>
            <script src="<?= BASE_URL ?>js/validation.js"></script>
            <script src="<?= BASE_URL ?>js/emergency-fix.js"></script>
    
    <?php if (isset($additionalScripts)): ?>
        <?php foreach ($additionalScripts as $script): ?>
            <script src="<?= BASE_URL . $script ?>"></script>
        <?php endforeach; ?>
    <?php endif; ?>
    
    <?php if (isset($inlineScript)): ?>
        <script><?= $inlineScript ?></script>
    <?php endif; ?>
</body>
</html>