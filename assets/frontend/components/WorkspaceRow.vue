<script setup>
import AvatarBadge from './AvatarBadge.vue';

defineProps({
  workspace: { type: Object, required: true },
  isArmedForDrag: { type: Boolean, required: true },
});

const emit = defineEmits(['open', 'arm-drag', 'drag-start', 'drag-end', 'drop']);
</script>

<template>
  <div
    class="flex items-stretch gap-3"
    :draggable="isArmedForDrag"
    @dragstart="emit('drag-start', $event)"
    @dragend="emit('drag-end')"
    @dragover.prevent
    @drop.prevent="emit('drop')"
  >
    <div
      class="flex w-6 shrink-0 items-center justify-center text-stone-400/50 transition hover:text-stone-500/70"
      data-drag-handle
      @pointerdown="emit('arm-drag')"
    >
      <i class="fa-solid fa-grip-lines text-sm" aria-hidden="true"></i>
      <span class="sr-only">Drag to reorder workspace</span>
    </div>

    <button
      type="button"
      class="group relative block w-full overflow-hidden rounded-[1.75rem] border px-5 py-4 text-left transition"
      :class="workspace.isSoloWorkspace ? 'border-stone-200 bg-stone-50 hover:border-stone-300' : 'border-amber-100 bg-amber-50/60 hover:border-amber-200'"
      @click="emit('open')"
    >
      <div class="relative z-10 flex flex-wrap items-center gap-x-6 gap-y-3 lg:flex-nowrap">
        <div class="min-w-0 flex-1">
          <div class="flex items-center gap-3">
            <div class="flex items-center gap-2">
              <span
                class="inline-flex h-10 min-w-10 items-center justify-center rounded-full px-3 text-sm font-semibold leading-none"
                :class="workspace.lateOpenItemCount > 0 ? 'bg-red-600 text-white' : 'border border-stone-200 bg-white/70 text-transparent'"
              >
                {{ workspace.lateOpenItemCount > 0 ? workspace.lateOpenItemCount : '0' }}
              </span>
              <span
                class="inline-flex h-10 min-w-10 items-center justify-center rounded-full bg-amber-100 px-3 text-sm font-semibold leading-none text-amber-800"
              >
                {{ workspace.isSoloWorkspace ? workspace.openItemCount : workspace.assignedOpenItemCount }}
              </span>
            </div>
            <h3 class="truncate text-lg font-semibold text-stone-950">{{ workspace.name }}</h3>
            <span v-if="workspace.isDefault" class="rounded-full bg-amber-100 px-2.5 py-1 text-[11px] font-semibold uppercase tracking-[0.16em] text-amber-700">Default</span>
          </div>
        </div>

        <template v-if="workspace.isSoloWorkspace">
          <div class="w-0 overflow-hidden"></div>
        </template>

        <template v-else>
          <div class="flex items-center gap-3 self-center text-sm font-semibold text-stone-800">
            <div class="flex h-10 items-center">
              <div
                v-for="(member, index) in workspace.membersPreview"
                :key="member.id ?? `${workspace.id}-${index}`"
                class="relative inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-full border-2 border-white bg-white"
                :class="index > 0 ? '-ml-3' : ''"
                :style="{ zIndex: workspace.membersPreview.length - index }"
              >
                <AvatarBadge
                  :seed="member.id ?? member.displayName"
                  :initials="member.initials"
                  :gravatar-url="member.gravatarUrl"
                  :alt="member.displayName"
                  :title="member.displayName || member.email || ''"
                />
              </div>
              <span
                v-if="workspace.memberCount > 7"
                class="ml-3 whitespace-nowrap text-sm font-medium text-stone-500"
              >
                ... +{{ workspace.memberCount - 7 }} members
              </span>
            </div>
          </div>
        </template>
      </div>
    </button>
  </div>
</template>
