<?php $__env->startSection('content'); ?>
<h1 class="text-xl font-bold mb-4">Usage Logs — <?php echo e($provider->name); ?></h1>
<div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-xl overflow-hidden">
    <table class="w-full text-sm">
        <thead class="bg-gray-50 dark:bg-gray-800 text-left text-gray-500">
            <tr><th class="p-3">Time</th><th class="p-3">Operation</th><th class="p-3">Status</th><th class="p-3">Response Time</th><th class="p-3">Error</th></tr>
        </thead>
        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
            <?php $__currentLoopData = $logs; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $log): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <tr>
                    <td class="p-3"><?php echo e($log->created_at); ?></td>
                    <td class="p-3"><?php echo e($log->operation); ?></td>
                    <td class="p-3"><?php echo e($log->status); ?></td>
                    <td class="p-3"><?php echo e($log->response_time); ?>ms</td>
                    <td class="p-3 text-red-600"><?php echo e($log->error_message); ?></td>
                </tr>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </tbody>
    </table>
</div>
<div class="mt-4"><?php echo e($logs->links()); ?></div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH D:\laravel project\ai-song-studio\resources\views\admin\providers\logs.blade.php ENDPATH**/ ?>