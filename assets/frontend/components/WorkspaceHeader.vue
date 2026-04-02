<script setup>
defineProps({
  workspace: { type: Object, required: true },
  isToastingMode: { type: Boolean, required: true },
  isSoloWorkspace: { type: Boolean, required: true },
  newToastCount: { type: Number, required: true },
  toastedToastCount: { type: Number, required: true },
  memberCount: { type: Number, required: true },
  backgroundVisible: { type: Boolean, required: true },
  workspaceUrl: { type: String, required: true },
  dashboardUrl: { type: String, required: true },
});

defineEmits(['open-manage', 'start-meeting', 'stop-meeting']);
</script>

<template>
  <div class="relative">
    <div class="relative z-10 space-y-2 px-6 py-8 lg:px-10">
      <div class="flex items-start justify-between gap-4">
        <div>
          <div class="flex flex-wrap items-center gap-3">
            <h1 class="inline-flex items-center gap-3 text-4xl font-semibold tracking-tight" :class="backgroundVisible ? 'text-white' : 'text-stone-950'">
              <i v-if="isToastingMode && !isSoloWorkspace" class="fa-solid fa-gear animate-spin [animation-duration:4s]" :class="backgroundVisible ? 'text-white/90' : 'text-amber-600'" aria-hidden="true"></i>
              <span>{{ workspace.name }}</span>
            </h1>
            <span v-if="workspace.isDefault" class="rounded-full px-3 py-1 text-xs font-semibold uppercase tracking-[0.18em]" :class="backgroundVisible ? 'bg-white/15 text-white' : 'bg-amber-100 text-amber-700'">Default workspace</span>
            <span v-if="isSoloWorkspace" class="rounded-full px-3 py-1 text-xs font-semibold uppercase tracking-[0.18em]" :class="backgroundVisible ? 'bg-white/15 text-white' : 'bg-stone-100 text-stone-700'">Solo workspace</span>
          </div>
          <div class="mt-3 flex flex-wrap gap-3 text-sm" :class="backgroundVisible ? 'text-white/85' : 'text-stone-500'">
            <span class="rounded-full px-3 py-1 font-medium" :class="backgroundVisible ? 'bg-white/15 text-white' : 'bg-stone-100 text-stone-700'">{{ newToastCount }} new toast<span v-if="newToastCount > 1">s</span></span>
            <span class="rounded-full px-3 py-1 font-medium" :class="backgroundVisible ? 'bg-white/15 text-white' : 'bg-stone-100 text-stone-700'">{{ toastedToastCount }} toasted toast<span v-if="toastedToastCount > 1">s</span></span>
            <span class="rounded-full px-3 py-1 font-medium" :class="backgroundVisible ? 'bg-white/15 text-white' : 'bg-stone-100 text-stone-700'">{{ memberCount }} member<span v-if="memberCount > 1">s</span></span>
          </div>
          <p v-if="workspace.isDefault" class="mt-2 text-sm" :class="backgroundVisible ? 'text-white/80' : 'text-stone-500'">This workspace is created automatically for every user and stays available as the permanent default.</p>
        </div>
        <div v-if="workspace.currentUserIsOwner" class="flex flex-wrap items-center justify-end gap-3">
          <button
            type="button"
            class="inline-grid h-12 w-12 place-items-center rounded-full border text-stone-700 transition"
            :class="backgroundVisible ? 'border-white/30 bg-white/15 text-white hover:border-white/50 hover:bg-white/20' : 'border-stone-200 bg-white hover:border-stone-300 hover:text-stone-950'"
            @click="$emit('open-manage')"
          >
            <i class="fa-solid fa-gear" aria-hidden="true"></i>
            <span class="sr-only">Manage workspace</span>
          </button>
          <button
            v-if="!isSoloWorkspace"
            type="button"
            class="inline-flex items-center gap-2 rounded-full px-5 py-3 text-sm font-semibold transition disabled:opacity-60"
            :class="workspace.meetingMode === 'live'
              ? (backgroundVisible ? 'bg-white text-stone-950 hover:bg-white/90' : 'bg-stone-900 text-white hover:bg-stone-800')
              : (backgroundVisible ? 'bg-amber-400 text-stone-950 hover:bg-amber-300' : 'bg-amber-500 text-stone-950 hover:bg-amber-400')"
            @click="workspace.meetingMode === 'live' ? $emit('stop-meeting') : $emit('start-meeting')"
          >
            <i v-if="workspace.meetingMode !== 'live'" class="fa-solid fa-bolt" aria-hidden="true"></i>
            <span>{{ workspace.meetingMode === 'live' ? 'Stop toasting mode' : 'Start toasting mode' }}</span>
          </button>
        </div>
      </div>
    </div>
  </div>
</template>
