<template>
    <div class="flex-1 flex flex-col min-h-0">
        <header class="h-16 bg-white border-b border-slate-200 flex items-center gap-3 px-4 sm:px-8 shrink-0 z-10 sticky top-0">
            <router-link :to="{ name: 'claim', params: { id } }" class="p-2 -ml-2 rounded-lg text-slate-400 hover:text-slate-700 hover:bg-slate-100 transition-colors" title="Back">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7 7-7m-7 7h18"/></svg>
            </router-link>
            <h1 class="font-black text-slate-900 text-lg tracking-tight">Sign Your Documents</h1>
        </header>

        <div class="flex-1 overflow-y-auto overflow-x-hidden bg-slate-100/70">
            <div class="max-w-3xl mx-auto px-4 sm:px-8 py-8">
                <div v-if="loading" class="text-sm text-slate-400 py-10 text-center">Loading…</div>
                <div v-else-if="error" class="bg-rose-50 border border-rose-100 text-rose-700 px-4 py-3 rounded-xl text-sm">{{ error }}</div>

                <template v-else-if="state">
                    <!-- Progress -->
                    <div class="flex items-center gap-2 mb-6 text-xs font-bold">
                        <span class="flex items-center gap-1.5 text-emerald-600"><span class="w-6 h-6 rounded-full bg-emerald-500 text-white flex items-center justify-center">✓</span> Confirm</span>
                        <span class="h-px flex-1 bg-slate-300"></span>
                        <span class="flex items-center gap-1.5" :class="state.all_signed ? 'text-emerald-600' : 'text-slate-900'">
                            <span class="w-6 h-6 rounded-full flex items-center justify-center" :class="state.all_signed ? 'bg-emerald-500 text-white' : 'bg-slate-900 text-white'">{{ state.all_signed ? '✓' : '2' }}</span> Sign
                        </span>
                        <span class="h-px flex-1 bg-slate-300"></span>
                        <span class="flex items-center gap-1.5 text-slate-400"><span class="w-6 h-6 rounded-full bg-slate-200 flex items-center justify-center">3</span> Filed</span>
                    </div>

                    <div v-if="state.all_signed" class="bg-white rounded-2xl ring-1 ring-slate-900/5 p-8 text-center mb-5">
                        <span class="w-14 h-14 rounded-full bg-emerald-500 text-white text-2xl flex items-center justify-center mx-auto mb-3">✓</span>
                        <h2 class="font-black text-slate-900 text-lg mb-1">All authorisations signed</h2>
                        <p class="text-sm text-slate-500 mb-4">Your claim is unlocked for filing - we take it from here and keep you posted.</p>
                        <router-link :to="{ name: 'claim', params: { id } }" class="inline-flex bg-slate-900 hover:bg-slate-800 text-white font-bold px-6 py-3 rounded-xl text-sm transition-colors">Back to your claim</router-link>
                    </div>

                    <div v-else class="bg-white rounded-2xl ring-1 ring-slate-900/5 p-6 mb-5">
                        <p class="text-sm text-slate-600">Each adult passenger signs their own Power of Attorney; a guardian signs for minors. The claim is filed as soon as every signature is in.</p>
                    </div>

                    <!-- Signers -->
                    <div class="space-y-3">
                        <div v-for="s in state.signers" :key="s.id" class="bg-white rounded-2xl ring-1 ring-slate-900/5 p-5">
                            <div class="flex items-center gap-3 flex-wrap">
                                <span class="w-9 h-9 rounded-full flex items-center justify-center shrink-0"
                                      :class="s.status === 'signed' ? 'bg-emerald-500 text-white' : 'bg-amber-100 text-amber-600 border border-amber-300'">
                                    <svg v-if="s.status === 'signed'" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                                    <svg v-else class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="9"/><path stroke-linecap="round" stroke-linejoin="round" d="M12 7v5l3 2"/></svg>
                                </span>
                                <div class="min-w-0 flex-1">
                                    <div class="font-bold text-slate-900 flex items-center gap-2 flex-wrap">
                                        {{ s.covers }}
                                        <span v-if="s.role === 'guardian'" class="text-[10px] font-black bg-violet-100 text-violet-700 px-2 py-0.5 rounded-full">GUARDIAN SIGNS</span>
                                    </div>
                                    <div class="text-xs text-slate-400">
                                        <template v-if="s.status === 'signed'">Signed {{ s.signed_at }}</template>
                                        <template v-else-if="s.invited_at">Signing request sent {{ s.invited_at }} to {{ s.email }}</template>
                                        <template v-else-if="s.signs_in_app">Ready for your signature</template>
                                        <template v-else>Needs their own signature - send them a signing request</template>
                                    </div>
                                </div>
                                <a v-if="s.poa_url" :href="s.poa_url" target="_blank" class="text-xs font-bold text-blue-600 hover:underline shrink-0">View POA</a>
                            </div>

                            <!-- Sign in-app -->
                            <div v-if="s.status === 'pending' && s.signs_in_app" class="mt-4">
                                <template v-if="openPad === s.id">
                                    <canvas :ref="(el) => (padEl = el)" class="w-full h-44 border-2 border-dashed border-slate-300 rounded-xl touch-none bg-white cursor-crosshair"></canvas>
                                    <div class="flex gap-2 mt-2">
                                        <button @click="clearPad" class="px-4 py-2.5 rounded-xl bg-slate-100 text-slate-600 text-sm font-bold">Clear</button>
                                        <button @click="submitPad(s)" :disabled="!drawn || signing" class="flex-1 px-4 py-2.5 rounded-xl bg-slate-900 hover:bg-slate-800 disabled:opacity-40 text-white text-sm font-bold transition-colors">
                                            {{ signing ? 'Saving…' : 'Sign & finish' }}
                                        </button>
                                    </div>
                                </template>
                                <button v-else @click="startSigning(s)" class="w-full sm:w-auto bg-slate-900 hover:bg-slate-800 text-white font-bold px-5 py-2.5 rounded-xl text-sm transition-colors">
                                    Sign now
                                </button>
                            </div>

                            <!-- Invite additional adult -->
                            <div v-else-if="s.status === 'pending' && !s.signs_in_app" class="mt-4">
                                <div class="flex flex-col sm:flex-row gap-2">
                                    <input v-model="invites[s.id]" type="email" :placeholder="s.email || `${s.name}'s email address`"
                                           @input="inviteErrors[s.id] = ''" @keyup.enter="invite(s)"
                                           class="flex-1 rounded-xl border bg-white px-4 py-2.5 text-sm placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-slate-900/10 transition-colors"
                                           :class="inviteErrors[s.id] ? 'border-rose-300 focus:border-rose-400' : 'border-slate-200 focus:border-slate-900'">
                                    <button @click="invite(s)" :disabled="inviting === s.id" class="bg-slate-900 hover:bg-slate-800 disabled:opacity-40 text-white font-bold px-5 py-2.5 rounded-xl text-sm transition-colors shrink-0">
                                        {{ inviting === s.id ? 'Sending…' : (s.invited_at ? 'Resend request' : 'Send signing request') }}
                                    </button>
                                </div>
                                <p v-if="inviteErrors[s.id]" class="text-xs font-bold text-rose-600 mt-1.5">{{ inviteErrors[s.id] }}</p>
                            </div>
                        </div>
                    </div>

                    <div v-if="state.assignment_url" class="mt-4 text-center">
                        <a :href="state.assignment_url" target="_blank" class="text-xs font-bold text-blue-600 hover:underline">View the Assignment of Claims document</a>
                    </div>
                </template>
            </div>
        </div>
    </div>
</template>

<script setup>
import { nextTick, onMounted, ref } from 'vue';
import api from '../api';

const props = defineProps({ id: { type: String, required: true } });

const state = ref(null);
const loading = ref(true);
const error = ref('');
const openPad = ref(null);
const padEl = ref(null);
const drawn = ref(false);
const signing = ref(false);
const inviting = ref(null);
const invites = ref({});
const inviteErrors = ref({});

let ctx = null;

function setupPad() {
    const canvas = padEl.value;
    if (!canvas) return;
    const r = canvas.getBoundingClientRect();
    canvas.width = r.width * 2;
    canvas.height = r.height * 2;
    ctx = canvas.getContext('2d');
    ctx.scale(2, 2);
    ctx.lineWidth = 2.2;
    ctx.lineCap = 'round';
    ctx.strokeStyle = '#0f172a';

    let drawing = false;
    const pos = (e) => {
        const rect = canvas.getBoundingClientRect();
        return [e.clientX - rect.left, e.clientY - rect.top];
    };
    canvas.onpointerdown = (e) => { drawing = true; const [x, y] = pos(e); ctx.beginPath(); ctx.moveTo(x, y); e.preventDefault(); };
    canvas.onpointermove = (e) => {
        if (!drawing) return;
        const [x, y] = pos(e);
        ctx.lineTo(x, y); ctx.stroke();
        drawn.value = true; e.preventDefault();
    };
    canvas.onpointerup = () => { drawing = false; };
    canvas.onpointerleave = () => { drawing = false; };
}

async function startSigning(signer) {
    // Dropbox Sign embedded experience when configured; built-in pad otherwise.
    if (state.value.mode === 'dropbox_sign') {
        try {
            const { sign_url, client_id } = await api.claims.signUrl(props.id, signer.id);
            if (sign_url && client_id) {
                await loadDropboxSign();
                const client = new window.HelloSign({ clientId: client_id });
                client.open(sign_url, { allowCancel: true, skipDomainVerification: true });
                client.on('sign', () => {
                    // Give the completion webhook a beat; the endpoint also
                    // reconciles with Dropbox directly, so the status is
                    // correct either way.
                    setTimeout(async () => { state.value = await api.claims.signers(props.id); }, 1500);
                });
                return;
            }
        } catch {
            // fall through to the pad
        }
    }
    openPad.value = signer.id;
    drawn.value = false;
    await nextTick();
    setupPad();
}

function loadDropboxSign() {
    if (window.HelloSign) return Promise.resolve();
    return new Promise((resolve, reject) => {
        const s = document.createElement('script');
        s.src = 'https://cdn.hellosign.com/public/js/embedded/v2.12.0/embedded.production.min.js';
        s.onload = resolve;
        s.onerror = reject;
        document.head.appendChild(s);
    });
}

function clearPad() {
    if (!padEl.value || !ctx) return;
    ctx.setTransform(1, 0, 0, 1, 0, 0);
    ctx.clearRect(0, 0, padEl.value.width, padEl.value.height);
    drawn.value = false;
    setupPad();
}

async function submitPad(signer) {
    if (!drawn.value || !padEl.value) return;
    signing.value = true;
    try {
        state.value = await api.claims.sign(props.id, signer.id, padEl.value.toDataURL('image/png'));
        openPad.value = null;
    } catch (e) {
        window.alert(e.response?.data?.message || 'Could not save your signature. Please try again.');
    } finally {
        signing.value = false;
    }
}

async function invite(signer) {
    const email = (invites.value[signer.id] || signer.email || '').trim();
    if (!/^[^@\s]+@[^@\s]+\.[^@\s]+$/.test(email)) {
        inviteErrors.value = { ...inviteErrors.value, [signer.id]: `Enter a valid email address for ${signer.name} first.` };
        return;
    }
    inviteErrors.value = { ...inviteErrors.value, [signer.id]: '' };
    inviting.value = signer.id;
    try {
        state.value = await api.claims.inviteSigner(props.id, signer.id, email);
    } catch (e) {
        inviteErrors.value = { ...inviteErrors.value, [signer.id]: e.response?.data?.message || 'Could not send the signing request. Please try again.' };
    } finally {
        inviting.value = null;
    }
}

onMounted(async () => {
    try {
        state.value = await api.claims.signers(props.id);
    } catch (e) {
        error.value = e.response?.data?.message || 'Could not load the signature stage.';
    } finally {
        loading.value = false;
    }
});
</script>
