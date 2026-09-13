<?php $__env->startSection('content'); ?>
<div class="max-w-sm mx-auto bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl p-8">
    <h1 class="text-xl font-bold mb-6">Log in</h1>
    <form method="POST" action="<?php echo e(route('login')); ?>" class="space-y-4">
        <?php echo csrf_field(); ?>
        <input type="email" name="email" placeholder="Email" required class="w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800">
        <input type="password" name="password" placeholder="Password" required class="w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800">
        <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="remember"> Remember me</label>
        <button class="w-full py-2 rounded-lg bg-indigo-600 text-white">Log in</button>
    </form>
    <div class="mt-4 text-sm flex justify-between">
        <a href="<?php echo e(route('register')); ?>" class="text-indigo-600 hover:underline">Create account</a>
        <a href="<?php echo e(route('password.request')); ?>" class="text-indigo-600 hover:underline">Forgot password?</a>
    </div>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH D:\laravel project\ai-song-studio\resources\views\auth\login.blade.php ENDPATH**/ ?>