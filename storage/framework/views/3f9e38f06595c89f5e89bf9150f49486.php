<?php $__env->startSection('content'); ?>

<div x-data="songStatus(<?php echo e($song->id); ?>, '<?php echo e($song->status); ?>')" x-init="init()" class="max-w-3xl mx-auto space-y-6">

    <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl p-8">
        <h1 class="text-2xl font-bold mb-1"><?php echo e($song->title); ?></h1>
        <p class="text-sm text-gray-500 mb-6"><?php echo e($song->genre); ?> · <?php echo e($song->mood); ?> · <?php echo e(ucfirst($song->vocal_type)); ?></p>

        <!-- Real-time generation status -->
        <div class="space-y-2 mb-6" x-show="status !== 'completed'">
            <template x-for="step in steps" :key="step.key">
                <div class="flex items-center gap-2 text-sm">
                    <span x-show="stepState(step.key) === 'done'">✅</span>
                    <span x-show="stepState(step.key) === 'active'">⏳</span>
                    <span x-show="stepState(step.key) === 'pending'">○</span>
                    <span x-text="step.label" :class="stepState(step.key) === 'pending' ? 'text-gray-400' : ''"></span>
                </div>
            </template>
            <div class="w-full bg-gray-100 dark:bg-gray-800 rounded-full h-2 mt-3">
                <div class="bg-indigo-600 h-2 rounded-full transition-all" :style="`width: ${progress}%`"></div>
            </div>
        </div>

        <div x-show="status === 'failed'" class="text-sm text-red-600 mb-4" x-text="errorMessage"></div>

        <!-- Player -->
        <div x-show="status === 'completed'" x-cloak>
            <audio controls class="w-full" :src="finalAudioUrl"></audio>
            <div class="flex gap-3 mt-4">
                <a href="<?php echo e(route('songs.download', [$song, 'mp3'])); ?>" class="px-4 py-2 rounded-lg bg-indigo-600 text-white text-sm">Download MP3</a>
                <a href="<?php echo e(route('songs.download', [$song, 'wav'])); ?>" class="px-4 py-2 rounded-lg border border-gray-300 dark:border-gray-700 text-sm">Download WAV</a>
            </div>
        </div>

        <?php if($song->lyrics): ?>
            <div class="mt-8">
                <h2 class="font-semibold mb-2">Lyrics</h2>
                <pre class="whitespace-pre-wrap text-sm text-gray-700 dark:text-gray-300 font-sans"><?php echo e($song->lyrics); ?></pre>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
function songStatus(songId, initialStatus) {
    return {
        status: initialStatus,
        stage: 'pending',
        progress: 0,
        errorMessage: null,
        finalAudioUrl: null,
        steps: [
            { key: 'lyrics_generated', label: 'Lyrics generated' },
            { key: 'music_generated', label: 'Instrumental generated' },
            { key: 'vocals_generated', label: 'Generating vocals' },
            { key: 'mixing', label: 'Mixing' },
            { key: 'completed', label: 'Finalizing' },
        ],
        stepOrder: ['pending','processing','lyrics_generated','music_generated','vocals_generated','mixing','completed'],
        stepState(key) {
            const cur = this.stepOrder.indexOf(this.stage);
            const target = this.stepOrder.indexOf(key);
            if (cur > target) return 'done';
            if (cur === target) return 'active';
            return 'pending';
        },
        init() {
            if (this.status === 'completed') return;
            this.poll();
        },
        async poll() {
            const res = await fetch(`/songs/${songId}/status`);
            const data = await res.json();
            this.stage = data.stage;
            this.status = data.status;
            this.progress = data.progress;
            this.errorMessage = data.error_message;
            this.finalAudioUrl = data.final_audio_url;

            if (this.status !== 'completed' && this.status !== 'failed') {
                setTimeout(() => this.poll(), 3000);
            }
        }
    }
}
</script>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH D:\laravel project\ai-song-studio\resources\views\songs\show.blade.php ENDPATH**/ ?>