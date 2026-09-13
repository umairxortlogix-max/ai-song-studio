<?php $__env->startSection('content'); ?>

<h1 class="text-2xl font-bold mb-6">AI Provider Router Monitor</h1>

<div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-xl overflow-hidden mb-8">
    <table class="w-full text-sm">
        <thead class="bg-gray-50 dark:bg-gray-800 text-left text-gray-500">
            <tr>
                <th class="p-3">Priority</th>
                <th class="p-3">Provider</th>
                <th class="p-3">Status</th>
                <th class="p-3">Usage Today</th>
                <th class="p-3">Model</th>
                <th class="p-3">Actions</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
            <?php $__currentLoopData = $providers; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $provider): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <tr>
                    <td class="p-3"><?php echo e($provider->priority); ?></td>
                    <td class="p-3 font-medium"><?php echo e($provider->name); ?></td>
                    <td class="p-3">
                        <span class="<?php echo \Illuminate\Support\Arr::toCssClasses([
                            'px-2 py-0.5 rounded-full text-xs',
                            'bg-green-100 text-green-700' => $provider->status === 'healthy',
                            'bg-yellow-100 text-yellow-700' => in_array($provider->status, ['limited','rate_limited']),
                            'bg-red-100 text-red-700' => in_array($provider->status, ['quota_exhausted','error']),
                            'bg-gray-100 text-gray-500' => $provider->status === 'disabled',
                        ]); ?>"><?php echo e(str_replace('_',' ', ucfirst($provider->status))); ?></span>
                    </td>
                    <td class="p-3">
                        <?php if($provider->daily_limit): ?>
                            <?php echo e($provider->used_today); ?>/<?php echo e($provider->daily_limit); ?> (<?php echo e($provider->usagePercent()); ?>%)
                        <?php else: ?>
                            n/a (unlimited/local)
                        <?php endif; ?>
                    </td>
                    <td class="p-3"><?php echo e($provider->model); ?></td>
                    <td class="p-3 space-x-2">
                        <form action="<?php echo e(route('admin.providers.toggle', $provider)); ?>" method="POST" class="inline">
                            <?php echo csrf_field(); ?>
                            <button class="text-indigo-600 hover:underline"><?php echo e($provider->is_active ? 'Disable' : 'Enable'); ?></button>
                        </form>
                        <form action="<?php echo e(route('admin.providers.reset', $provider)); ?>" method="POST" class="inline">
                            <?php echo csrf_field(); ?>
                            <button class="text-indigo-600 hover:underline">Reset Usage</button>
                        </form>
                        <a href="<?php echo e(route('admin.providers.logs', $provider)); ?>" class="text-indigo-600 hover:underline">Logs</a>
                        <button type="button"
                                @click="fetch('<?php echo e(route('admin.providers.test', $provider)); ?>', {method:'POST', headers:{'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content}}).then(r=>r.json()).then(d=>alert(d.ok ? 'Healthy' : 'Unhealthy: ' + d.status))"
                                class="text-indigo-600 hover:underline">
                            Test Connection
                        </button>
                    </td>
                </tr>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </tbody>
    </table>
</div>

<h2 class="text-lg font-semibold mb-3">Add Provider</h2>
<form action="<?php echo e(route('admin.providers.store')); ?>" method="POST" class="grid grid-cols-2 gap-3 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-xl p-6">
    <?php echo csrf_field(); ?>
    <input name="name" placeholder="Name" class="rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800" required>
    <input name="slug" placeholder="unique-slug" class="rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800" required>
    <select name="provider_type" class="rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800" required>
        <?php $__currentLoopData = array_keys(config('ai.provider_classes')); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $type): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <option value="<?php echo e($type); ?>"><?php echo e($type); ?></option>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
    </select>
    <input name="model" placeholder="Model name" class="rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800">
    <input name="api_base_url" placeholder="https://api.example.com" class="rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800">
    <input name="api_key" placeholder="API Key" type="password" class="rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800">
    <input name="priority" type="number" placeholder="Priority (1 = first)" class="rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800" required>
    <input name="daily_limit" type="number" placeholder="Daily limit" class="rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800">
    <input name="monthly_limit" type="number" placeholder="Monthly limit" class="rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800">
    <button class="col-span-2 py-2 rounded-lg bg-indigo-600 text-white">Add Provider</button>
</form>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH D:\laravel project\ai-song-studio\resources\views\admin\providers\index.blade.php ENDPATH**/ ?>