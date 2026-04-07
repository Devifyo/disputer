<style>
    /* ─── SKELETON BASE ─── */
    .sk-block {
        background: #e2e8f0;
        border-radius: 8px;
        position: relative;
        overflow: hidden;
    }
    .sk-block::after {
        content: '';
        position: absolute;
        inset: 0;
        background: linear-gradient(90deg, transparent 0%, rgba(255,255,255,0.7) 50%, transparent 100%);
        background-size: 200% 100%;
        animation: sk-sweep 1.4s infinite ease-in-out;
    }
    @keyframes sk-sweep {
        0%   { background-position: 200% 0; }
        100% { background-position: -200% 0; }
    }

    /* ─── SKELETON OVERLAY ─── */
    #page-skeleton {
        position: fixed;
        inset: 0;
        background: #f8fafc;
        z-index: 9999;
        overflow-y: auto;
        padding: 0;
        transition: opacity 0.5s ease;
    }
    #page-skeleton.sk-hidden {
        opacity: 0;
        pointer-events: none;
    }

    /* ─── SKELETON NAV ─── */
    .sk-nav {
        height: 64px;
        background: #fff;
        border-bottom: 1px solid #e2e8f0;
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 0 48px;
    }
    .sk-nav-logo { display: flex; align-items: center; gap: 10px; }

    /* ─── SKELETON HERO ─── */
    .sk-hero {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 60px;
        padding: 120px 48px 80px;
        max-width: 1300px;
        margin: 0 auto;
    }
    .sk-hero-left { display: flex; flex-direction: column; gap: 16px; padding-top: 20px; }
    .sk-hero-right { display: flex; flex-direction: column; gap: 16px; }
    .sk-card {
        background: #fff;
        border: 1px solid #e2e8f0;
        border-radius: 20px;
        padding: 24px 28px;
        display: flex;
        flex-direction: column;
        gap: 10px;
    }

    /* ─── SKELETON SECTION ─── */
    .sk-section {
        padding: 80px 48px;
        max-width: 1300px;
        margin: 0 auto;
    }
    .sk-section-dark {
        background: #0f172a;
        padding: 80px 48px;
    }
    .sk-section-dark .sk-block { background: #1e293b; }
    .sk-section-dark .sk-block::after {
        background: linear-gradient(90deg, transparent 0%, rgba(255,255,255,0.05) 50%, transparent 100%);
        background-size: 200% 100%;
    }
    .sk-section-cream { background: #f8fafc; padding: 80px 48px; }

    .sk-grid-4 { display: grid; grid-template-columns: repeat(4,1fr); gap: 24px; margin-top: 40px; }
    .sk-grid-3 { display: grid; grid-template-columns: repeat(3,1fr); gap: 24px; margin-top: 40px; }
    .sk-grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 40px; align-items: center; }
    .sk-grid-3-dark { display: grid; grid-template-columns: repeat(3,1fr); gap: 2px; border-radius: 24px; overflow: hidden; margin-top: 40px; }
    .sk-item { padding: 28px; display: flex; gap: 14px; background: rgba(255,255,255,0.03); }

    @media (max-width: 900px) {
        .sk-hero { grid-template-columns: 1fr; padding: 100px 24px 60px; }
        .sk-hero-right { display: none; }
        .sk-grid-4 { grid-template-columns: 1fr 1fr; }
        .sk-grid-3 { grid-template-columns: 1fr; }
        .sk-grid-2 { grid-template-columns: 1fr; }
        .sk-section, .sk-section-dark, .sk-section-cream { padding: 60px 24px; }
        .sk-nav { padding: 0 24px; }
    }
    @media (max-width: 560px) {
        .sk-grid-4 { grid-template-columns: 1fr; }
    }

    /* ─── REAL CONTENT hidden until skeleton removed ─── */
    #page-content {
        opacity: 0;
        transition: opacity 0.5s ease;
    }
    #page-content.sk-ready {
        opacity: 1;
    }
</style>
