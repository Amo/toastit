<script setup>
import { Datepicker } from 'flowbite';
import { nextTick, onMounted, onUnmounted, ref, watch } from 'vue';
import FormField from './FormField.vue';

const model = defineModel({ default: '' });

const props = defineProps({
  label: { type: String, required: true },
  help: { type: String, default: '' },
  placeholder: { type: String, default: 'Select date' },
  hideLabel: { type: Boolean, default: false },
  inputClass: { type: String, default: '' },
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

watch(isMobileViewport, async (isMobile) => {
  await nextTick();

  if (isMobile) {
    instance?.destroyAndRemoveInstance?.();
    instance = null;
    return;
  }

  if (!inputRef.value || instance) {
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
</script>

<template>
  <FormField :label="label" :help="help" :hide-label="hideLabel">
    <input
      ref="inputRef"
      :class="['rounded-xl border border-stone-200 bg-white px-3 py-2 text-sm', inputClass]"
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
