<script setup>
import { EditorContent, useEditor } from '@tiptap/vue-3';
import StarterKit from '@tiptap/starter-kit';
import { Markdown } from '@tiptap/markdown';
import { computed, ref, watch } from 'vue';

const props = defineProps({
  modelValue: { type: String, default: '' },
  placeholder: { type: String, default: '' },
  minHeightClass: { type: String, default: 'min-h-[10rem]' },
  blocked: { type: Boolean, default: false },
  compact: { type: Boolean, default: false },
});

const emit = defineEmits(['update:modelValue', 'focus', 'blur', 'keydown']);
const isSyncingContent = ref(false);

const normalizedValue = computed(() => String(props.modelValue ?? '').trim());

const editor = useEditor({
  extensions: [
    StarterKit,
    Markdown,
  ],
  content: normalizedValue.value,
  contentType: 'markdown',
  editorProps: {
    attributes: {
      class: 'outline-none',
    },
    handleDOMEvents: {
      keydown: (_view, event) => {
        emit('keydown', event);
        return false;
      },
      focus: () => {
        emit('focus');
        return false;
      },
      blur: () => {
        emit('blur');
        return false;
      },
    },
  },
  onUpdate: ({ editor: currentEditor }) => {
    if (isSyncingContent.value) {
      return;
    }

    emit('update:modelValue', currentEditor.getMarkdown().trim());
  },
});

watch(normalizedValue, (nextValue) => {
  if (!editor.value) {
    return;
  }

  const currentValue = editor.value.getMarkdown().trim();
  if (currentValue === nextValue) {
    return;
  }

  isSyncingContent.value = true;
  editor.value.commands.setContent(nextValue, { contentType: 'markdown' });
  isSyncingContent.value = false;
});

defineExpose({
  focusEnd: () => {
    editor.value?.commands.focus('end');
  },
});
</script>

<template>
  <div
    class="markdown-rich-editor rounded-[1.5rem] border bg-white transition"
    :class="[
      blocked ? 'border-red-400 ring-2 ring-red-100' : 'border-stone-200',
      compact ? 'px-4 py-3' : 'px-5 py-4',
    ]"
  >
    <EditorContent
      v-if="editor"
      :editor="editor"
      class="text-stone-900"
      :class="[minHeightClass, compact ? 'text-sm leading-6' : 'text-base leading-7']"
    />
    <p v-if="!normalizedValue" class="pointer-events-none mt-0 text-stone-400" :class="compact ? 'text-sm leading-6' : 'text-base leading-7'">
      {{ placeholder }}
    </p>
  </div>
</template>

<style scoped>
.markdown-rich-editor :deep(.ProseMirror) {
  color: rgb(28 25 23);
  white-space: pre-wrap;
}

.markdown-rich-editor :deep(.ProseMirror > *) {
  margin: 0;
}

.markdown-rich-editor :deep(.ProseMirror > * + *) {
  margin-top: 0.65rem;
}

.markdown-rich-editor :deep(.ProseMirror h1),
.markdown-rich-editor :deep(.ProseMirror h2),
.markdown-rich-editor :deep(.ProseMirror h3),
.markdown-rich-editor :deep(.ProseMirror h4),
.markdown-rich-editor :deep(.ProseMirror h5) {
  color: rgb(12 10 9);
  font-weight: 700;
  letter-spacing: -0.02em;
  line-height: 1.1;
}

.markdown-rich-editor :deep(.ProseMirror h1) {
  font-size: 1.85rem;
}

.markdown-rich-editor :deep(.ProseMirror h2) {
  font-size: 1.45rem;
}

.markdown-rich-editor :deep(.ProseMirror h3) {
  font-size: 1.2rem;
}

.markdown-rich-editor :deep(.ProseMirror ul),
.markdown-rich-editor :deep(.ProseMirror ol) {
  padding-left: 1.5rem;
}

.markdown-rich-editor :deep(.ProseMirror ul) {
  list-style: disc;
}

.markdown-rich-editor :deep(.ProseMirror ol) {
  list-style: decimal;
}

.markdown-rich-editor :deep(.ProseMirror blockquote) {
  border-left: 3px solid rgb(251 191 36);
  color: rgb(87 83 78);
  padding-left: 1rem;
}

.markdown-rich-editor :deep(.ProseMirror code) {
  background: rgb(245 245 244);
  border-radius: 0.375rem;
  font-size: 0.92em;
  padding: 0.15rem 0.35rem;
}

.markdown-rich-editor :deep(.ProseMirror pre) {
  background: rgb(28 25 23);
  border-radius: 1rem;
  color: white;
  overflow-x: auto;
  padding: 1rem;
}

.markdown-rich-editor :deep(.ProseMirror hr) {
  border: 0;
  border-top: 1px solid rgb(231 229 228);
  margin: 1.25rem 0;
}
</style>
