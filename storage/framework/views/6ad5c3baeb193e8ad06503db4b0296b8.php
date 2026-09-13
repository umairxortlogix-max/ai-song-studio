<?php $__env->startSection('content'); ?>
<div class="max-w-md mx-auto bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl p-8 text-center">
    <h1 class="text-xl font-bold mb-4">Verify your email</h1>
    <p class="text-sm text-gray-500 mb-6">We've sent a verification link to your email address.</p>
    <form method="POST" action="<?php echo e(route('verification.send')); ?>">
        <?php echo csrf_field(); ?>
        <button class="px-4 py-2 rounded-lg bg-indigo-600 text-white text-sm">Resend verification email</button>
    </form>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH D:\laravel project\ai-song-studio\resources\views\auth\verify-email.blade.php ENDPATH**/ ?>