<script setup>
import AvatarBadge from './AvatarBadge.vue';

const props = defineProps({
  title: { type: String, required: true },
  variant: { type: String, default: 'active' },
  accentClass: { type: String, default: 'border-stone-200' },
  owner: { type: Object, default: null },
  dueOnDisplay: { type: String, default: '' },
  author: { type: Object, default: null },
  commentsCount: { type: Number, default: 0 },
});

const containerClass = {
  active: 'bg-white transition hover:shadow-toastit-panel',
  vetoed: 'border-stone-200 bg-white opacity-95 transition hover:shadow-toastit-panel',
  resolved: 'border-stone-200 bg-white opacity-95 transition hover:shadow-toastit-panel',
};
</script>

<template>
  <article
    class="overflow-hidden rounded-[1.35rem] border"
    :class="variant === 'active' ? [accentClass, 'bg-white transition hover:shadow-toastit-panel'] : containerClass[variant]"
  >
    <button type="button" class="flex w-full items-start justify-between gap-4 px-5 py-4 text-left" @click="$emit('open')">
      <div class="min-w-0 space-y-2">
        <div class="flex items-center gap-3">
          <AvatarBadge
            v-if="owner"
            :seed="owner.id"
            :initials="owner.initials"
            :gravatar-url="owner.gravatarUrl"
            :alt="owner.displayName"
            :title="owner.displayName"
          />
          <p class="truncate text-lg font-semibold text-stone-950">{{ title }}</p>
        </div>
        <div class="flex flex-wrap items-center gap-x-4 gap-y-2 text-xs text-stone-400">
          <span v-if="dueOnDisplay" class="inline-flex items-center gap-1.5">
            <i class="fa-regular fa-calendar" aria-hidden="true"></i>
            <span>{{ dueOnDisplay }}</span>
          </span>
          <span v-if="author?.displayName" class="inline-flex items-center gap-1.5">
            <i class="fa-regular fa-user" aria-hidden="true"></i>
            <span>{{ author.displayName }}</span>
          </span>
          <span class="inline-flex items-center gap-1.5">
            <i class="fa-regular fa-comment" aria-hidden="true"></i>
            <span>{{ commentsCount }}</span>
          </span>
        </div>
      </div>
      <div class="shrink-0">
        <slot name="actions" />
      </div>
    </button>
  </article>
</template>
