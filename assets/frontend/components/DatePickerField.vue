<script setup>
import { Datepicker } from 'flowbite';
import { onMounted, onUnmounted, ref, watch } from 'vue';
import FormField from './FormField.vue';

const model = defineModel({ default: '' });

const props = defineProps({
  label: { type: String, required: true },
  help: { type: String, default: '' },
  placeholder: { type: String, default: 'Select date' },
});

const inputRef = ref(null);
const isMobileViewport = ref(false);
let instance = null;

const syncViewport = () => {
  isMobileViewport.value = window.innerWidth < 1024;
};

const syncFromPicker = () => {
  model.value = inputRef.value?.value ?? '';
};

const syncToPicker = () => {
  if (!instance) {
    return;
  }

  if (!model.value) {
    instance.setDate({ clear: true });
    return;
  }

  instance.setDate(model.value);
};

onMounted(() => {
  syncViewport();
  window.addEventListener('resize', syncViewport);

  if (isMobileViewport.value) {
    return;
  }

  if (!inputRef.value) {
    return;
  }

  instance = new Datepicker(inputRef.value, {
    autohide: true,
    format: 'yyyy-mm-dd',
    buttons: true,
    todayHighlight: true,
    weekStart: 1,
  });

  inputRef.value.addEventListener('changeDate', syncFromPicker);
  syncToPicker();
});

onUnmounted(() => {
  window.removeEventListener('resize', syncViewport);

  if (inputRef.value) {
    inputRef.value.removeEventListener('changeDate', syncFromPicker);
  }

  instance?.destroyAndRemoveInstance?.();
  instance = null;
});

watch(model, () => {
  syncToPicker();
});
</script>

<template>
  <FormField :label="label" :help="help">
    <input
      ref="inputRef"
      class="rounded-2xl border border-stone-200 bg-white px-4 py-3 text-sm"
      :type="isMobileViewport ? 'date' : 'text'"
      :value="model"
      :placeholder="placeholder"
      :readonly="!isMobileViewport"
      :datepicker="isMobileViewport ? null : true"
      :datepicker-buttons="isMobileViewport ? null : true"
      :datepicker-autohide="isMobileViewport ? null : true"
      :datepicker-format="isMobileViewport ? null : 'yyyy-mm-dd'"
      :datepicker-orientation="isMobileViewport ? null : 'bottom'"
      @input="model = $event.target.value"
    >
  </FormField>
</template>
