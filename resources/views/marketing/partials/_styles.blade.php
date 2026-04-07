<style>
    /* ─── EXACT HOME STYLES ─── */
    header {
        min-height: 100vh;
        padding: 140px 48px 80px;
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 60px;
        align-items: center;
        position: relative;
        overflow: hidden;
    }
    .hero-bg-shape {
        position: absolute; right: -100px; top: 50%;
        transform: translateY(-50%);
        width: 600px; height: 600px;
        border-radius: 50%;
        background: radial-gradient(circle, rgba(37, 99, 235, 0.12) 0%, transparent 70%);
        pointer-events: none;
    }
    .hero-tag {
        display: inline-flex; align-items: center; gap: 8px;
        padding: 6px 14px;
        border: 1px solid rgba(37, 99, 235, 0.2);
        border-radius: 100px;
        font-size: 0.75rem; font-weight: 600; letter-spacing: 0.08em;
        text-transform: uppercase; color: var(--accent);
        background: rgba(37, 99, 235, 0.06);
        margin-bottom: 28px;
    }
    .hero-tag span { width: 6px; height: 6px; background: var(--accent); border-radius: 50%; display: block; }
    h1 {
        font-size: clamp(3rem, 4.5vw, 4.5rem);
        font-weight: 800;
        line-height: 1.05;
        letter-spacing: -0.035em;
        color: var(--ink);
        margin-bottom: 28px;
    }
    h1 em {
        font-style: normal;
        color: var(--accent);
        position: relative;
    }
    h1 em::after {
        content: '';
        position: absolute; bottom: 4px; left: 0; right: 0;
        height: 3px;
        background: var(--accent-light);
        border-radius: 2px;
    }
    .hero-sub {
        font-size: 1.15rem; color: var(--muted);
        line-height: 1.7; font-weight: 400;
        max-width: 480px;
        margin-bottom: 40px;
    }
    .hero-actions { display: flex; gap: 16px; align-items: center; flex-wrap: wrap; }
    .btn-secondary {
        display: inline-flex; align-items: center; gap: 8px;
        padding: 14px 28px; border-radius: 12px;
        font-size: 1rem; font-weight: 600;
        color: var(--ink); background: transparent;
        border: 1px solid var(--border);
        transition: all 0.2s; text-decoration: none;
    }
    .btn-secondary:hover { background: rgba(15, 23, 42, 0.03); border-color: rgba(15, 23, 42, 0.2); }
    .hero-visual {
        position: relative;
        display: flex; flex-direction: column; gap: 16px;
    }
    .hero-card {
        background: var(--white);
        border: 1px solid var(--border);
        border-radius: 20px;
        padding: 24px 28px;
        box-shadow: 0 2px 20px rgba(15, 23, 42, 0.05);
        transition: transform 0.3s;
        width: 100%;
    }
    .hero-card:hover { transform: translateX(-6px); }
    .hero-card-label { font-size: 0.7rem; font-weight: 700; letter-spacing: 0.1em; text-transform: uppercase; color: var(--muted); margin-bottom: 8px; }
    .hero-card-title { font-size: 1rem; font-weight: 700; color: var(--ink); margin-bottom: 4px; letter-spacing: -0.01em; }
    .hero-card-body { font-size: 0.875rem; color: var(--muted); line-height: 1.5; font-weight: 400; }
    .hero-card-badge {
        display: inline-flex; align-items: center; gap: 5px;
        margin-top: 12px; padding: 5px 12px;
        border-radius: 100px; font-size: 0.72rem; font-weight: 700;
        letter-spacing: 0.04em; text-transform: uppercase;
    }
    .badge-resolved { background: #dcfce7; color: #15803d; }
    .badge-inprogress { background: #dbeafe; color: #1d4ed8; }

    /* ─── HOW IT WORKS ─── */
    #how-it-works { padding: 120px 48px; background: var(--white); border-bottom: 1px solid var(--border); }
    .how-inner { max-width: 1200px; margin: 0 auto; }
    .how-header { text-align: center; max-width: 600px; margin: 0 auto 60px; }
    .how-header h2 { color: var(--ink); margin-top: 16px; }
    .steps-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 24px; }
    .step-card { background: var(--paper); border: 1px solid var(--border); border-radius: 24px; padding: 36px 28px; position: relative; transition: transform 0.3s; }
    .step-card:hover { transform: translateY(-4px); box-shadow: 0 12px 30px rgba(15, 23, 42, 0.04); }
    .step-number { display: inline-flex; align-items: center; justify-content: center; background: rgba(37, 99, 235, 0.1); color: var(--accent); width: 48px; height: 48px; border-radius: 14px; font-size: 1.25rem; font-weight: 800; margin-bottom: 24px; }
    .step-title { font-size: 1.15rem; font-weight: 700; color: var(--ink); margin-bottom: 12px; letter-spacing: -0.01em; }
    .step-desc { font-size: 0.95rem; color: var(--muted); line-height: 1.6; font-weight: 400; }

    /* ─── WHY ─── */
    #why-this-exists { padding: 120px 48px; background: var(--ink); color: var(--paper); position: relative; overflow: hidden; }
    #why-this-exists::before { content: 'WHY'; position: absolute; right: -20px; top: -40px; font-weight: 800; font-size: 20rem; color: rgba(255,255,255,0.02); line-height: 1; pointer-events: none; user-select: none; }
    .why-inner { max-width: 1200px; margin: 0 auto; display: grid; grid-template-columns: 1fr 1fr; gap: 80px; align-items: center; }
    .section-eyebrow { font-size: 0.72rem; font-weight: 700; letter-spacing: 0.12em; text-transform: uppercase; color: var(--accent-light); margin-bottom: 20px; display: flex; align-items: center; gap: 12px; }
    .section-eyebrow::before { content: ''; display: block; width: 32px; height: 2px; background: var(--accent-light); border-radius: 2px; }
    h2 { font-size: clamp(2rem, 3.5vw, 3.2rem); font-weight: 800; line-height: 1.1; letter-spacing: -0.03em; }
    .why-right { display: flex; flex-direction: column; gap: 28px; }
    .why-right p { font-size: 1.1rem; line-height: 1.8; color: rgba(248, 250, 252, 0.65); font-weight: 300; }
    .why-highlight { padding: 28px; border: 1px solid rgba(248, 250, 252, 0.1); border-left: 3px solid var(--accent); border-radius: 0 16px 16px 0; background: rgba(37, 99, 235, 0.1); }
    .why-highlight p { font-size: 1.2rem; font-weight: 500; color: var(--paper) !important; line-height: 1.6; }

    /* ─── STORY ─── */
    #story { padding: 120px 48px; background: var(--cream); }
    .story-inner { max-width: 800px; margin: 0 auto; text-align: center; }
    .story-inner h2 { margin-bottom: 48px; color: var(--ink); }
    .story-body { text-align: left; display: flex; flex-direction: column; gap: 20px; }
    .story-body p { font-size: 1.15rem; line-height: 1.85; color: #334155; font-weight: 400; }
    .story-body p strong { font-weight: 700; font-size: 1.5rem; color: var(--ink); display: block; margin-top: 12px; letter-spacing: -0.02em; }

    /* ─── OUTCOMES ─── */
    #outcomes { padding: 120px 48px; background: var(--paper); }
    .outcomes-inner { max-width: 1200px; margin: 0 auto; }
    .outcomes-header { display: grid; grid-template-columns: 1fr 1fr; gap: 60px; align-items: end; margin-bottom: 60px; }
    .outcomes-header h2 { color: var(--ink); }
    .outcomes-header p { font-size: 1.05rem; line-height: 1.8; color: var(--muted); font-weight: 400; }
    .outcomes-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 24px; align-items: stretch; }
    .outcome-card { background: var(--white); border: 1px solid var(--border); border-radius: 24px; padding: 36px; display: flex; flex-direction: column; position: relative; transition: all 0.3s; }
    .outcome-card:hover { transform: translateY(-6px); box-shadow: 0 20px 60px rgba(15, 23, 42, 0.08); }
    .outcome-body { flex-grow: 1; margin-bottom: 20px; }
    .outcome-tag { display: inline-block; padding: 4px 12px; background: #e2e8f0; border-radius: 100px; font-size: 0.7rem; font-weight: 700; letter-spacing: 0.08em; text-transform: uppercase; color: #475569; margin-bottom: 16px; }
    .outcome-card h3 { font-size: 1.2rem; font-weight: 700; color: var(--ink); margin-bottom: 16px; letter-spacing: -0.02em; }
    .outcome-card p { font-size: 0.95rem; color: var(--muted); line-height: 1.7; font-weight: 400; }
    .outcome-result { padding-top: 20px; border-top: 1px solid var(--border); display: flex; align-items: center; gap: 8px; min-height: 3.5rem; }
    .outcome-result svg { width: 16px; height: 16px; flex-shrink: 0; }

    /* ─── SITUATIONS ─── */
    #situations { padding: 120px 48px; background: var(--ink); }
    .situations-inner { max-width: 1200px; margin: 0 auto; }
    .situations-header { margin-bottom: 60px; }
    .situations-header h2 { color: var(--paper); }
    .situations-header p { font-size: 1.1rem; color: rgba(248, 250, 252, 0.6); line-height: 1.7; font-weight: 300; margin-top: 20px; max-width: 560px; }
    .sit-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 2px; border: 2px solid rgba(248, 250, 252, 0.08); border-radius: 24px; overflow: hidden; }
    .sit-item { padding: 28px; background: rgba(248, 250, 252, 0.02); display: flex; align-items: flex-start; gap: 16px; transition: background 0.2s; cursor: default; border-right: 1px solid rgba(248, 250, 252, 0.05); border-bottom: 1px solid rgba(248, 250, 252, 0.05); }
    .sit-item:hover { background: rgba(37, 99, 235, 0.1); }
    .sit-icon { width: 44px; height: 44px; flex-shrink: 0; background: rgba(248, 250, 252, 0.06); border-radius: 12px; display: flex; align-items: center; justify-content: center; transition: background 0.2s; }
    .sit-item:hover .sit-icon { background: var(--accent); }
    .sit-icon svg { width: 20px; height: 20px; color: rgba(248, 250, 252, 0.5); transition: color 0.2s; }
    .sit-item:hover .sit-icon svg { color: white; }
    .sit-text { font-size: 0.95rem; font-weight: 500; color: rgba(248, 250, 252, 0.7); line-height: 1.4; padding-top: 10px; transition: color 0.2s; }
    .sit-item:hover .sit-text { color: var(--paper); }

    /* ─── FAQ ─── */
    #faq { padding: 120px 48px; background: var(--cream); }
    .faq-inner { max-width: 800px; margin: 0 auto; }
    .faq-header { text-align: center; margin-bottom: 60px; }
    .faq-header h2 { color: var(--ink); margin-top: 16px; }
    details.faq-item { border-bottom: 1px solid var(--border); padding: 24px 0; }
    details.faq-item summary::-webkit-details-marker { display: none; }
    details.faq-item summary { list-style: none; font-size: 1.15rem; font-weight: 700; color: var(--ink); cursor: pointer; display: flex; justify-content: space-between; align-items: center; letter-spacing: -0.01em; }
    details.faq-item summary svg { flex-shrink: 0; color: var(--muted); transition: transform 0.3s ease; }
    details.faq-item[open] summary svg { transform: rotate(180deg); color: var(--accent); }
    .faq-answer { padding-top: 16px; padding-right: 40px; font-size: 1.05rem; color: #334155; line-height: 1.7; }

    /* ─── CTA ─── */
    #cta { padding: 80px 48px 120px; background: var(--ink); }
    .cta-inner { max-width: 1200px; margin: 0 auto; }
    .cta-box { background: var(--accent); border-radius: 32px; padding: 80px; display: grid; grid-template-columns: 1fr auto; gap: 60px; align-items: center; position: relative; overflow: hidden; }
    .cta-box::after { content: ''; position: absolute; right: -80px; top: -80px; width: 400px; height: 400px; background: rgba(255,255,255,0.08); border-radius: 50%; }
    .cta-box::before { content: ''; position: absolute; right: 120px; bottom: -100px; width: 250px; height: 250px; background: rgba(0,0,0,0.08); border-radius: 50%; }
    .cta-left { position: relative; z-index: 1; }
    .cta-left h2 { color: white; margin-bottom: 16px; font-size: clamp(2rem, 3vw, 3rem); }
    .cta-left p { color: rgba(255,255,255,0.8); font-size: 1.1rem; line-height: 1.7; font-weight: 400; }
    .cta-right { position: relative; z-index: 1; flex-shrink: 0; }

    /* ─── ANIMATIONS ─── */
    @keyframes fadeUp { from { opacity: 0; transform: translateY(24px); } to { opacity: 1; transform: translateY(0); } }
    @keyframes slideIn { from { opacity: 0; transform: translateX(20px); } to { opacity: 1; transform: translateX(0); } }
    .hero-left > * { opacity: 0; animation: fadeUp 0.8s cubic-bezier(0.16, 1, 0.3, 1) forwards; }
    .hero-left > *:nth-child(1) { animation-delay: 0.1s; }
    .hero-left > *:nth-child(2) { animation-delay: 0.2s; }
    .hero-left > *:nth-child(3) { animation-delay: 0.35s; }
    .hero-left > *:nth-child(4) { animation-delay: 0.5s; }
    .hero-visual .hero-card { opacity: 0; animation: slideIn 0.7s cubic-bezier(0.16, 1, 0.3, 1) forwards; }
    .hero-visual .hero-card:nth-child(1) { animation-delay: 0.5s; }
    .hero-visual .hero-card:nth-child(2) { animation-delay: 0.65s; }
    .hero-visual .hero-card:nth-child(3) { animation-delay: 0.8s; }

    /* ─── RESPONSIVE ─── */
    @media (max-width: 1024px) { .steps-grid { grid-template-columns: repeat(2, 1fr); } }
    @media (max-width: 900px) {
        header { grid-template-columns: 1fr; padding: 110px 24px 60px; }
        .hero-visual { display: none; }
        #how-it-works { padding: 80px 24px; }
        #why-this-exists { padding: 80px 24px; }
        .why-inner { grid-template-columns: 1fr; gap: 40px; }
        #story { padding: 80px 24px; }
        #outcomes { padding: 80px 24px; }
        .outcomes-header { grid-template-columns: 1fr; }
        .outcomes-grid { grid-template-columns: 1fr; }
        #situations { padding: 80px 24px; }
        .sit-grid { grid-template-columns: 1fr 1fr; }
        #faq { padding: 80px 24px; }
        #cta { padding: 40px 24px 80px; }
        .cta-box { grid-template-columns: 1fr; padding: 48px; gap: 32px; }
    }
    @media (max-width: 560px) {
        .steps-grid { grid-template-columns: 1fr; }
        .sit-grid { grid-template-columns: 1fr; }
        .hero-actions { flex-direction: column; align-items: stretch; }
        .hero-actions .btn-primary, .hero-actions .btn-secondary { width: 100%; justify-content: center; }
    }
</style>
