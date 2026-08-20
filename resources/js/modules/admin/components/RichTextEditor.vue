<script setup>
import { useEditor, EditorContent } from '@tiptap/vue-3';
import StarterKit from '@tiptap/starter-kit';
import Link from '@tiptap/extension-link';
import TextAlign from '@tiptap/extension-text-align';
import Underline from '@tiptap/extension-underline';
import { watch } from 'vue';

const props = defineProps({
    modelValue: { type: String, default: '' },
    dir: { type: String, default: 'ltr' },
    placeholder: { type: String, default: '' },
});

const emit = defineEmits(['update:modelValue']);

const editor = useEditor({
    content: props.modelValue,
    extensions: [
        StarterKit.configure({
            heading: { levels: [1, 2, 3] },
        }),
        Link.configure({
            openOnClick: false,
            HTMLAttributes: { target: '_blank', rel: 'noopener noreferrer' },
        }),
        TextAlign.configure({ types: ['heading', 'paragraph'] }),
        Underline,
    ],
    editorProps: {
        attributes: {
            class: 'prose prose-sm max-w-none min-h-[200px] px-4 py-3 outline-none focus:outline-none',
            dir: props.dir,
        },
    },
    onUpdate: ({ editor: e }) => {
        emit('update:modelValue', e.getHTML());
    },
});

watch(() => props.modelValue, (val) => {
    if (editor.value && editor.value.getHTML() !== val) {
        editor.value.commands.setContent(val, false);
    }
});

watch(() => props.dir, (dir) => {
    if (editor.value) {
        editor.value.setOptions({
            editorProps: {
                attributes: {
                    class: 'prose prose-sm max-w-none min-h-[200px] px-4 py-3 outline-none focus:outline-none',
                    dir,
                },
            },
        });
    }
});

const setLink = () => {
    if (!editor.value) return;

    const prev = editor.value.getAttributes('link').href ?? '';
    const url = window.prompt('URL', prev);

    if (url === null) return;

    if (url === '') {
        editor.value.chain().focus().extendMarkRange('link').unsetLink().run();
        return;
    }

    editor.value.chain().focus().extendMarkRange('link').setLink({ href: url }).run();
};

const btnClass = (active) =>
    active
        ? 'rounded-lg bg-slate-900 px-2 py-1.5 text-white'
        : 'rounded-lg px-2 py-1.5 text-slate-600 hover:bg-slate-100';
</script>

<template>
    <div class="overflow-hidden rounded-xl border border-slate-300">
        <div v-if="editor" class="flex flex-wrap items-center gap-0.5 border-b border-slate-200 bg-slate-50 px-2 py-1.5">
            <button type="button" :class="btnClass(editor.isActive('heading', { level: 1 }))" :title="'Heading 1'" @click="editor.chain().focus().toggleHeading({ level: 1 }).run()">
                <span class="text-xs font-bold">H1</span>
            </button>
            <button type="button" :class="btnClass(editor.isActive('heading', { level: 2 }))" :title="'Heading 2'" @click="editor.chain().focus().toggleHeading({ level: 2 }).run()">
                <span class="text-xs font-bold">H2</span>
            </button>
            <button type="button" :class="btnClass(editor.isActive('heading', { level: 3 }))" :title="'Heading 3'" @click="editor.chain().focus().toggleHeading({ level: 3 }).run()">
                <span class="text-xs font-bold">H3</span>
            </button>

            <span class="mx-1 h-5 w-px bg-slate-300" />

            <button type="button" :class="btnClass(editor.isActive('bold'))" title="Bold" @click="editor.chain().focus().toggleBold().run()">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M6 4h8a4 4 0 0 1 0 8H6zM6 12h9a4 4 0 0 1 0 8H6z"/></svg>
            </button>
            <button type="button" :class="btnClass(editor.isActive('italic'))" title="Italic" @click="editor.chain().focus().toggleItalic().run()">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M10 4h4m-2 0 -4 16m-2 0h4"/></svg>
            </button>
            <button type="button" :class="btnClass(editor.isActive('underline'))" title="Underline" @click="editor.chain().focus().toggleUnderline().run()">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M6 4v6a6 6 0 0 0 12 0V4M4 20h16"/></svg>
            </button>

            <span class="mx-1 h-5 w-px bg-slate-300" />

            <button type="button" :class="btnClass(editor.isActive('bulletList'))" title="Bullet list" @click="editor.chain().focus().toggleBulletList().run()">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M9 6h11M9 12h11M9 18h11M5 6h.01M5 12h.01M5 18h.01"/></svg>
            </button>
            <button type="button" :class="btnClass(editor.isActive('orderedList'))" title="Numbered list" @click="editor.chain().focus().toggleOrderedList().run()">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M10 6h11M10 12h11M10 18h11M4 6h1v4M4 10h2M4 16.5a1.5 1.5 0 0 1 3 0 1.5 1.5 0 0 1-3 0"/></svg>
            </button>

            <span class="mx-1 h-5 w-px bg-slate-300" />

            <button type="button" :class="btnClass(editor.isActive('link'))" title="Link" @click="setLink">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg>
            </button>

            <span class="mx-1 h-5 w-px bg-slate-300" />

            <button type="button" :class="btnClass(editor.isActive({ textAlign: 'left' }))" title="Align left" @click="editor.chain().focus().setTextAlign('left').run()">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M4 6h16M4 10h10M4 14h16M4 18h10"/></svg>
            </button>
            <button type="button" :class="btnClass(editor.isActive({ textAlign: 'center' }))" title="Align center" @click="editor.chain().focus().setTextAlign('center').run()">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M4 6h16M7 10h10M4 14h16M7 18h10"/></svg>
            </button>
            <button type="button" :class="btnClass(editor.isActive({ textAlign: 'right' }))" title="Align right" @click="editor.chain().focus().setTextAlign('right').run()">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M4 6h16M10 10h10M4 14h16M10 18h10"/></svg>
            </button>

            <span class="mx-1 h-5 w-px bg-slate-300" />

            <button type="button" class="rounded-lg px-2 py-1.5 text-slate-600 hover:bg-slate-100" title="Undo" @click="editor.chain().focus().undo().run()">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M3 10h10a5 5 0 0 1 0 10H9M3 10l4-4M3 10l4 4"/></svg>
            </button>
            <button type="button" class="rounded-lg px-2 py-1.5 text-slate-600 hover:bg-slate-100" title="Redo" @click="editor.chain().focus().redo().run()">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21 10H11a5 5 0 0 0 0 10h4M21 10l-4-4M21 10l-4 4"/></svg>
            </button>
        </div>

        <EditorContent :editor="editor" />
    </div>
</template>

<style>
.ProseMirror {
    min-height: 200px;
}
.ProseMirror:focus {
    outline: none;
}
.ProseMirror h1 { font-size: 1.5rem; font-weight: 700; margin-bottom: 0.5rem; }
.ProseMirror h2 { font-size: 1.25rem; font-weight: 600; margin-bottom: 0.5rem; }
.ProseMirror h3 { font-size: 1.1rem; font-weight: 600; margin-bottom: 0.5rem; }
.ProseMirror p { margin-bottom: 0.5rem; }
.ProseMirror ul { list-style: disc; padding-left: 1.5rem; margin-bottom: 0.5rem; }
.ProseMirror ol { list-style: decimal; padding-left: 1.5rem; margin-bottom: 0.5rem; }
.ProseMirror a { color: #0ea5e9; text-decoration: underline; }
.ProseMirror blockquote { border-left: 3px solid #e2e8f0; padding-left: 1rem; color: #64748b; }
</style>
