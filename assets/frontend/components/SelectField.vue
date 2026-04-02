<script setup>
import FormField from './FormField.vue';

const model = defineModel({ default: '' });

const props = defineProps({
  label: { type: String, required: true },
  help: { type: String, default: '' },
});
</script>

<template>
  <FormField :label="label" :help="help">
    <select
      class="rounded-2xl border border-stone-200 bg-white px-4 py-3 text-sm"
      :value="model"
      @change="model = $event.target.value"
    >
      <slot />
    </select>
  </FormField>
</template>
