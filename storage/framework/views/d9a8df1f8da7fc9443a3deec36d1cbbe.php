<?php $__env->startSection('content'); ?>

<div class="grid grid-cols-2 md:grid-cols-5 gap-4 mb-8">
    <?php $__currentLoopData = [
        'Total Songs' => $stats['total'],
        'Completed' => $stats['completed'],
        'Processing' => $stats['processing'],
        'Failed' => $stats['failed'],
        'Remaining Today' => $stats['remaining_today'],
    ]; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $label => $value): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
        <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-xl p-4">
            <div class="text-2xl font-bold"><?php echo e($value); ?></div>
            <div class="text-xs text-gray-500"><?php echo e($label); ?></div>
        </div>
    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
</div>

<div class="flex items-center justify-between mb-4">
    <h2 class="text-lg font-semibold">My Songs</h2>
    <a href="<?php echo e(route('songs.create')); ?>" class="text-sm text-indigo-600 hover:underline">+ Create New Song</a>
</div>

<div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-xl overflow-hidden">
    <table class="w-full text-sm">
        <thead class="bg-gray-50 dark:bg-gray-800 text-left text-gray-500">
            <tr>
                <th class="p-3">Title</th>
                <th class="p-3">Genre</th>
                <th class="p-3">Duration</th>
                <th class="p-3">Status</th>
                <th class="p-3">Created</th>
                <th class="p-3">Actions</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
            <?php $__empty_1 = true; $__currentLoopData = $songs; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $song): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                <tr>
                    <td class="p-3 font-medium"><?php echo e($song->title); ?></td>
                    <td class="p-3"><?php echo e($song->genre); ?></td>
                    <td class="p-3"><?php echo e($song->duration ? gmdate('i:s', $song->duration) : '—'); ?></td>
                    <td class="p-3">
                        <span class="<?php echo \Illuminate\Support\Arr::toCssClasses([
                            'px-2 py-0.5 rounded-full text-xs',
                            'bg-green-100 text-green-700' => $song->status === 'completed',
                            'bg-yellow-100 text-yellow-700' => in_array($song->status, ['pending','processing']),
                            'bg-red-100 text-red-700' => $song->status === 'failed',
                        ]); ?>"><?php echo e(ucfirst($song->status)); ?></span>
                    </td>
                    <td class="p-3"><?php echo e($song->created_at->diffForHumans()); ?></td>
                    <td class="p-3 space-x-2">
                        <a href="<?php echo e(route('songs.show', $song)); ?>" class="text-indigo-600 hover:underline">Open</a>
                        <?php if($song->status === 'completed'): ?>
                            <a href="<?php echo e(route('songs.download', [$song, 'mp3'])); ?>" class="text-indigo-600 hover:underline">MP3</a>
                        <?php endif; ?>
                        <form action="<?php echo e(route('songs.destroy', $song)); ?>" method="POST" class="inline" onsubmit="return confirm('Delete this song?')">
                            <?php echo csrf_field(); ?> <?php echo method_field('DELETE'); ?>
                            <button class="text-red-600 hover:underline">Delete</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                <tr><td colspan="6" class="p-6 text-center text-gray-400">No songs yet — create your first one!</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<div class="mt-4"><?php echo e($songs->links()); ?></div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH D:\laravel project\ai-song-studio\resources\views\songs\dashboard.blade.php ENDPATH**/ ?>