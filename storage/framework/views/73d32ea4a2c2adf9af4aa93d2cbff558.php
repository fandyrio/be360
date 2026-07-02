<?php
    use Knuckles\Scribe\Tools\Utils as u;
?>
# <?php echo e(u::trans("scribe::headings.auth")); ?>


<?php if(!$isAuthed): ?>
<?php echo u::trans("scribe::auth.none"); ?>

<?php else: ?>
<?php echo $authDescription; ?>


<?php echo $extraAuthInfo; ?>

<?php endif; ?>
<?php /**PATH /var/www/html/be_360/be360/vendor/knuckleswtf/scribe/src/../resources/views//markdown/auth.blade.php ENDPATH**/ ?>