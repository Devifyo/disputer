<template>
    <div class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-slate-900/50" @click="$emit('close')"></div>

        <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-lg max-h-[90vh] overflow-y-auto">
            <!-- Header -->
            <div class="px-6 py-5 border-b border-slate-100 flex items-center justify-between">
                <div>
                    <h2 class="font-bold text-slate-900">{{ option.label }}</h2>
                    <p class="text-xs text-slate-400 mt-0.5">A few quick questions - each one adapts to your answers.</p>
                </div>
                <button @click="$emit('close')" class="p-2 -mr-2 rounded-lg text-slate-400 hover:text-slate-600 hover:bg-slate-100 transition-colors" aria-label="Close">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" d="M6 6l12 12M18 6L6 18"/></svg>
                </button>
            </div>

            <!-- Progress: answered dots + current -->
            <div class="px-6 pt-4 flex items-center gap-1.5">
                <span v-for="(entry, i) in qa" :key="i" class="h-1.5 w-6 rounded-full bg-slate-900"></span>
                <span v-if="!done" class="h-1.5 w-6 rounded-full" :class="fetching ? 'bg-slate-300 animate-pulse' : 'bg-slate-400'"></span>
                <span v-else class="h-1.5 w-6 rounded-full bg-emerald-500"></span>
            </div>

            <!-- Thinking about the next question -->
            <div v-if="fetching" class="px-6 py-12 text-center">
                <svg class="w-7 h-7 mx-auto text-primary-500 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"/></svg>
                <p class="text-sm text-slate-500 mt-3">{{ qa.length ? 'Choosing the next question…' : 'Preparing your questions…' }}</p>
            </div>

            <!-- Current question -->
            <div v-else-if="!done && question" class="p-6">
                <p class="text-[11px] uppercase tracking-wider font-bold text-slate-400 mb-2">Question {{ qa.length + 1 }}</p>
                <h3 class="font-bold text-slate-900 mb-4">{{ question.question }}</h3>

                <div v-if="question.type === 'choice'" class="space-y-2">
                    <button
                        v-for="opt in question.options"
                        :key="opt"
                        @click="draft = opt"
                        class="w-full text-left px-4 py-3 rounded-xl ring-1 text-sm font-medium transition-all"
                        :class="draft === opt
                            ? 'bg-slate-900 text-white ring-slate-900'
                            : 'bg-white text-slate-700 ring-slate-200 hover:ring-slate-300 hover:bg-slate-50'"
                    >{{ opt }}</button>
                </div>
                <textarea
                    v-else
                    v-model.trim="draft"
                    rows="4"
                    maxlength="1000"
                    placeholder="Type your answer…"
                    class="w-full px-4 py-3 rounded-xl ring-1 ring-slate-200 border-0 text-sm text-slate-700 focus:ring-2 focus:ring-primary-500 transition-shadow"
                ></textarea>
            </div>

            <!-- Documents step -->
            <div v-else-if="done" class="p-6">
                <h3 class="font-bold text-slate-900 mb-1">{{ documents.title }}</h3>
                <p class="text-xs text-slate-500 mb-4">{{ documents.note }}</p>

                <div class="rounded-xl bg-slate-50 p-4 mb-4">
                    <p class="text-[11px] uppercase tracking-wider font-bold text-slate-400 mb-2">Examples of what helps</p>
                    <ul class="space-y-1.5">
                        <li v-for="ex in documents.examples" :key="ex" class="flex gap-2 text-sm text-slate-600">
                            <svg class="w-4 h-4 text-emerald-500 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4"/></svg>
                            {{ ex }}
                        </li>
                    </ul>
                </div>

                <label class="flex flex-col items-center justify-center gap-2 border-2 border-dashed border-slate-200 hover:border-primary-300 rounded-xl px-4 py-6 cursor-pointer transition-colors">
                    <svg class="w-6 h-6 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M12 16V4m0 0L8 8m4-4l4 4M4 16v2a2 2 0 002 2h12a2 2 0 002-2v-2"/></svg>
                    <span class="text-sm font-bold text-slate-600">Add documents</span>
                    <span class="text-xs text-slate-400">PDF or photos · up to 5 files · 8 MB each</span>
                    <input type="file" multiple accept=".pdf,.jpg,.jpeg,.png,.webp,.heic,.heif" class="hidden" @change="addFiles" />
                </label>

                <ul v-if="files.length" class="mt-3 space-y-2">
                    <li v-for="(f, i) in files" :key="i" class="flex items-center gap-2 px-3 py-2 rounded-lg bg-slate-50 text-sm">
                        <svg class="w-4 h-4 text-primary-500 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 4H7a2 2 0 01-2-2V6a2 2 0 012-2h7l5 5v11a2 2 0 01-2 2z"/></svg>
                        <span class="truncate flex-1 text-slate-700">{{ f.name }}</span>
                        <button @click="files.splice(i, 1)" class="text-slate-300 hover:text-rose-500 transition-colors" aria-label="Remove file">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" d="M6 6l12 12M18 6L6 18"/></svg>
                        </button>
                    </li>
                </ul>
            </div>

            <!-- Footer -->
            <div v-if="!fetching" class="px-6 py-4 bg-slate-50 border-t border-slate-100 flex items-center justify-between gap-3">
                <button
                    v-if="qa.length"
                    @click="back"
                    class="px-4 py-2.5 rounded-xl text-sm font-bold text-slate-500 hover:text-slate-700 hover:bg-slate-100 transition-colors"
                >Back</button>
                <span v-else></span>

                <button
                    v-if="!done"
                    @click="next"
                    :disabled="!draft"
                    class="px-6 py-2.5 bg-slate-900 hover:bg-slate-800 text-white rounded-xl text-sm font-bold shadow-lg shadow-slate-900/10 transition-all active:scale-95 disabled:opacity-40"
                >Next</button>
                <button
                    v-else
                    @click="submit"
                    :disabled="submitting"
                    class="px-6 py-2.5 bg-slate-900 hover:bg-slate-800 text-white rounded-xl text-sm font-bold shadow-lg shadow-slate-900/10 transition-all active:scale-95 disabled:opacity-60"
                >{{ files.length ? 'Submit report' : 'Submit without documents' }}</button>
            </div>
        </div>
    </div>
</template>

<script setup>
import { onMounted, ref } from 'vue';
import api from '../api';
import { showProcessing } from '../confirm';

// Adaptive intake funnel: every answer is sent back to the AI, which picks
// the next question (or ends the questions and moves to documents).
const props = defineProps({
    tripId: { type: [String, Number], required: true },
    option: { type: Object, required: true }, // { type, label }
});
const emit = defineEmits(['close', 'submitted']);

const fetching = ref(true);
const submitting = ref(false);
const qa = ref([]);            // [{ q: questionObject, answer }]
const question = ref(null);    // current question object
const draft = ref('');
const done = ref(false);
const documents = ref({ title: 'Supporting documents', note: '', examples: [] });
const files = ref([]);

function answersPayload() {
    return qa.value.map((entry) => ({ question: entry.q.question, answer: entry.answer }));
}

async function fetchNext() {
    fetching.value = true;
    try {
        const res = await api.trips.reportQuestions(props.tripId, props.option.type, answersPayload());
        if (res.done) {
            done.value = true;
            question.value = null;
            if (res.documents) documents.value = res.documents;
        } else {
            done.value = false;
            question.value = res.question;
            draft.value = '';
        }
    } catch (e) {
        window.alert('Could not load the next question. Please try again.');
        emit('close');
    } finally {
        fetching.value = false;
    }
}

function next() {
    qa.value.push({ q: question.value, answer: draft.value });
    fetchNext();
}

// Going back re-opens the previous answer; the questions after it will be
// re-chosen by the AI, since a changed answer can change the path.
function back() {
    const last = qa.value.pop();
    done.value = false;
    question.value = last.q;
    draft.value = last.answer;
}

function addFiles(event) {
    const incoming = Array.from(event.target.files || []);
    files.value = [...files.value, ...incoming].slice(0, 5);
    event.target.value = '';
}

async function submit() {
    submitting.value = true;
    const doneDialog = showProcessing(
        'Checking your eligibility…',
        'We\'re reviewing your report against EU261, UK261, APPR and US DOT rules. This usually takes a few seconds.'
    );
    try {
        const form = new FormData();
        form.append('type', props.option.type);
        answersPayload().forEach((entry, i) => {
            form.append(`answers[${i}][question]`, entry.question);
            form.append(`answers[${i}][answer]`, entry.answer);
        });
        files.value.forEach((f, i) => form.append(`documents[${i}]`, f));

        const res = await api.trips.report(props.tripId, form);
        emit('submitted', res.data);
    } catch (e) {
        window.alert(e.response?.data?.message || 'Could not submit the report. Please try again.');
    } finally {
        doneDialog();
        submitting.value = false;
    }
}

onMounted(fetchNext);
</script>
