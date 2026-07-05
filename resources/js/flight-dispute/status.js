export const itineraryStatusMeta = {
    uploaded:   { label: 'Uploaded',   cls: 'bg-slate-100 text-slate-600 border-slate-200' },
    processing: { label: 'Processing', cls: 'bg-amber-50 text-amber-700 border-amber-200' },
    parsed:     { label: 'Processed',  cls: 'bg-emerald-50 text-emerald-700 border-emerald-200' },
    failed:     { label: 'Failed',     cls: 'bg-rose-50 text-rose-700 border-rose-200' },
};

export function itinMeta(status) {
    return itineraryStatusMeta[status] || itineraryStatusMeta.uploaded;
}

const claimStatusCls = {
    draft:                       'bg-slate-100 text-slate-600 border-slate-200',
    pending_eligibility_review:  'bg-blue-50 text-blue-700 border-blue-100',
};

export function claimCls(status) {
    return claimStatusCls[status] || claimStatusCls.draft;
}
