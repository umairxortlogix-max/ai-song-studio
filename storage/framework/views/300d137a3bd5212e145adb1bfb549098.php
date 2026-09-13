<?php $__env->startSection('content'); ?>
<div class="max-w-sm mx-auto bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl p-8">
    <h1 class="text-xl font-bold mb-6">Set a new password</h1>
    <form method="POST" action="<?php echo e(route('password.store')); ?>" class="space-y-4">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="token" value="<?php echo e($request->route('token')); ?>">
        <input type="email" name="email" placeholder="Email" value="<?php echo e(old('email', $request->email)); ?>" required class="w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800">
        <input type="password" name="password" placeholder="New password" required class="w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800">
        <input type="password" name="password_confirmation" placeholder="Confirm new password" required class="w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800">
        <button class="w-full py-2 rounded-lg bg-indigo-600 text-white">Reset password</button>
    </form>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH D:\laravel project\ai-song-studio\resources\views\auth\reset-password.blade.php ENDPATH**/ ?>