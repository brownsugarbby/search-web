import Alpine from 'alpinejs';

const RECENT_KEY = 'recent-searches';
const RECENT_MAX = 6;

/** Remember a term for this visitor only. Never leaves the browser. */
function remember(term) {
    if (!term || term.trim().length < 2) return;

    try {
        const kept = JSON.parse(localStorage.getItem(RECENT_KEY) ?? '[]');
        const next = [term.trim(), ...kept.filter((t) => t !== term.trim())].slice(0, RECENT_MAX);
        localStorage.setItem(RECENT_KEY, JSON.stringify(next));
    } catch {
        // Private windows and blocked site data throw on access. A missing
        // convenience is not worth breaking the search over.
    }
}

/**
 * Typeahead over the curated keyword list.
 *
 * Suggestions carry their destination, so picking one goes straight there
 * rather than round-tripping through a search.
 */
Alpine.data('suggest', (initial = '', keepRecent = false) => ({
    term: initial,
    keepRecent,
    items: [],
    open: false,
    active: -1,

    /*
     * Whether the current term leads anywhere, answered by /resolve while the
     * visitor types. The form reads it to decide its target: a match opens the
     * destination in a new tab, anything else stays in this one.
     *
     * It starts false and returns to false the moment the term changes, so an
     * unanswered check submits in-tab. That is the safe direction to be wrong
     * in - a search that stays put is ordinary, a tab that opens onto "no
     * results" is the thing being fixed.
     */
    hit: false,
    checkTimer: null,

    /*
     * Call this as `$data.checkTarget()`, never bare. Invoked bare from an
     * x-on expression, `this` is Alpine's temporary evaluator scope rather
     * than the component, and the write below - which happens 250ms later,
     * after that scope is gone - lands nowhere at all.
     */
    checkTarget() {
        const state = this;

        clearTimeout(state.checkTimer);
        state.hit = false;

        const q = state.term.trim();

        if (q === '') return;

        state.checkTimer = setTimeout(async () => {
            try {
                const res = await fetch(`/resolve?q=${encodeURIComponent(q)}`, {
                    headers: { Accept: 'application/json' },
                });

                // The term may have moved on while this was in flight.
                if (!res.ok || q !== state.term.trim()) return;

                state.hit = (await res.json()).hit === true;
            } catch {
                // Offline, blocked, rate-limited: leave it in-tab.
            }
        }, 250);
    },

    focusInput() {
        this.$refs.input?.focus();
    },

    async fetch() {
        const q = this.term.trim();

        if (q.length < 2) return this.close();

        try {
            const res = await fetch(`/suggest?q=${encodeURIComponent(q)}`, {
                headers: { Accept: 'application/json' },
            });
            this.items = res.ok ? await res.json() : [];
            this.open = this.items.length > 0;
            this.active = -1;
        } catch {
            this.close();
        }
    },

    move(delta) {
        if (!this.open || !this.items.length) return;
        this.active = (this.active + delta + this.items.length) % this.items.length;
    },

    onEnter(event) {
        // Only when the feature is on. Writing to a visitor's browser for
        // something that is never displayed back to them is just litter.
        if (this.keepRecent) remember(this.term);

        // Only intercept Enter when something in the dropdown is highlighted;
        // otherwise let the form submit normally.
        if (this.open && this.active >= 0) {
            event.preventDefault();
            // New tab, matching what clicking the same row does.
            window.open(`/go/${this.items[this.active].slug}`, '_blank', 'noopener');
        }
    },

    clear() {
        this.term = '';
        this.close();
        this.focusInput();
    },

    close() {
        this.open = false;
        this.items = [];
        this.active = -1;
    },
}));

Alpine.data('recentSearches', () => ({
    items: [],

    init() {
        try {
            this.items = JSON.parse(localStorage.getItem(RECENT_KEY) ?? '[]');
        } catch {
            this.items = [];
        }
    },

    clear() {
        try {
            localStorage.removeItem(RECENT_KEY);
        } catch { /* nothing to do */ }
        this.items = [];
    },
}));

/**
 * Share one catalog entry.
 *
 * Native sheet first - this audience is overwhelmingly on phones, and the OS
 * sheet already contains whichever app they actually use. The dropdown is only
 * the desktop fallback.
 */
Alpine.data('share', ({ url, title }) => ({
    url,
    title,
    open: false,
    copied: false,

    async go() {
        if (navigator.share) {
            try {
                await navigator.share({ title: this.title, url: this.url });
                return;
            } catch (err) {
                // Dismissing the sheet is not a failure worth falling back on.
                if (err.name === 'AbortError') return;
            }
        }

        this.open = !this.open;
    },

    async copy() {
        try {
            await navigator.clipboard.writeText(this.url);
            this.copied = true;
            setTimeout(() => { this.copied = false; this.open = false; }, 1200);
        } catch {
            window.prompt('Salin tautan ini:', this.url);
        }
    },
}));

window.Alpine = Alpine;
Alpine.start();
