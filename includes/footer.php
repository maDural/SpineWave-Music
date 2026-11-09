    </div>
</div>
<?php require __DIR__ . '/player.php'; ?>
<?php
$footerUser = current_user();
$footerPlaylists = [];
if ($footerUser) {
    $q = $pdo->prepare('SELECT id,name FROM playlists WHERE user_id=:uid ORDER BY name');
    $q->execute(['uid'=>$footerUser['id']]);
    $footerPlaylists = $q->fetchAll();
}
?>
<script>
window.SPINWAVE = <?= json_encode([
    'loggedIn'=>(bool)$footerUser,
    'csrfToken'=>csrf_token(),
    'playlists'=>$footerPlaylists,
], JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP) ?>;
</script>
<script src="assets/js/app.js"></script>
<script src="https://www.youtube.com/iframe_api"></script>
</body></html>
