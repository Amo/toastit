<script setup>
import AvatarBadge from './AvatarBadge.vue';

defineProps({
  membership: { type: Object, required: true },
  workspaceCurrentUserIsOwner: { type: Boolean, required: true },
  ownerCount: { type: Number, required: true },
});

const emit = defineEmits(['promote', 'demote', 'remove']);
</script>

<template>
  <div class="flex items-center justify-between gap-4 rounded-xl border border-stone-200 px-4 py-3">
    <div class="flex items-center gap-3">
      <AvatarBadge
        :seed="membership.user.id"
        :initials="membership.user.initials"
        :gravatar-url="membership.user.gravatarUrl"
        :alt="membership.user.displayName"
      />
      <div>
        <p class="font-medium text-stone-900">{{ membership.user.displayName }}</p>
        <p class="text-sm text-stone-500">{{ membership.user.email }}</p>
      </div>
    </div>
    <div class="flex flex-wrap items-center justify-end gap-2">
      <span v-if="membership.isOwner" class="rounded-full bg-stone-100 px-3 py-1 text-xs font-semibold text-stone-700">Owner</span>
      <template v-if="workspaceCurrentUserIsOwner">
        <button
          v-if="!membership.isOwner"
          type="button"
          class="rounded-full border border-stone-200 px-4 py-2 text-sm font-medium text-stone-700"
          @click="emit('promote', membership.id)"
        >
          Promote
        </button>
        <button
          v-else-if="ownerCount > 1"
          type="button"
          class="rounded-full border border-stone-200 px-4 py-2 text-sm font-medium text-stone-700"
          @click="emit('demote', membership.id)"
        >
          Demote
        </button>
        <button
          v-if="!membership.isOwner || ownerCount > 1"
          type="button"
          class="rounded-full border border-stone-200 px-4 py-2 text-sm font-medium text-stone-700"
          @click="emit('remove', membership.id)"
        >
          Remove
        </button>
      </template>
    </div>
  </div>
</template>
