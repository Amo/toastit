<script setup>
defineProps({
  workspace: { type: Object, required: true },
  isArmedForDrag: { type: Boolean, required: true },
});

const emit = defineEmits(['open', 'arm-drag', 'drag-start', 'drag-end', 'drop']);
</script>

<template>
  <div
    class="flex items-stretch gap-2"
    :draggable="isArmedForDrag"
    @dragstart="emit('drag-start', $event)"
    @dragend="emit('drag-end')"
    @dragover.prevent
    @drop.prevent="emit('drop')"
  >
    <div
      class="flex w-5 shrink-0 items-center justify-center text-stone-300 transition hover:text-stone-500"
      data-drag-handle
      @pointerdown="emit('arm-drag')"
    >
      <i class="fa-solid fa-grip-lines text-xs" aria-hidden="true"></i>
      <span class="sr-only">Drag to reorder workspace</span>
    </div>

    <button
      type="button"
      class="flex w-full items-center gap-3 rounded-[1.2rem] border px-3 py-3 text-left transition"
      :class="workspace.isSoloWorkspace ? 'border-stone-200 bg-white hover:border-stone-300' : 'border-amber-100 bg-amber-50/50 hover:border-amber-200'"
      @click="emit('open')"
    >
      <div class="flex items-center gap-2">
        <span
          class="inline-flex h-8 min-w-8 items-center justify-center rounded-full px-2 text-xs font-semibold leading-none"
          :class="workspace.lateOpenItemCount > 0 ? 'bg-red-600 text-white' : 'border border-stone-200 bg-stone-50 text-stone-400'"
        >
          {{ workspace.lateOpenItemCount > 0 ? workspace.lateOpenItemCount : '0' }}
        </span>
        <span class="inline-flex h-8 min-w-8 items-center justify-center rounded-full bg-amber-100 px-2 text-xs font-semibold leading-none text-amber-800">
          {{ workspace.isSoloWorkspace ? workspace.openItemCount : workspace.assignedOpenItemCount }}
        </span>
      </div>

      <div class="min-w-0 flex-1">
        <div class="flex items-center gap-2">
          <h3 class="truncate text-sm font-semibold text-stone-950">{{ workspace.name }}</h3>
          <span v-if="workspace.isDefault" class="rounded-full bg-amber-100 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-[0.16em] text-amber-700">Default</span>
        </div>
        <p class="mt-1 text-xs text-stone-500">
          {{ workspace.isSoloWorkspace ? 'Solo workspace' : `${workspace.memberCount} members` }}
        </p>
      </div>
    </button>
  </div>
</template>
