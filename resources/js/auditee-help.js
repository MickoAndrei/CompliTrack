import Alpine from 'alpinejs';

const normalize = value => value.toLocaleLowerCase().normalize('NFKC').replace(/[^\p{L}\p{N}]+/gu, ' ').trim();

Alpine.data('auditeeHelp', () => ({
    query: '',
    selected: 'prepare',
    topics: [],
    init() {
        // Index the guidance itself so search cannot drift from the visible content.
        this.topics = [...this.$el.querySelectorAll('[data-help-topic]')].map(panel => ({
            id: panel.id, label: panel.dataset.helpTopic,
            text: normalize(`${panel.dataset.helpTopic} ${panel.textContent}`),
        }));
        this.selected = this.topics[0]?.id ?? '';
        this.onHashChange = () => {
            const id = window.location.hash.slice(1);
            if (this.topics.some(topic => topic.id === id)) {
                this.query = '';
                this.selected = id;
            }
        };
        this.onHashChange();
        window.addEventListener('hashchange', this.onHashChange);
        this.$watch('query', () => {
            if (!this.filteredTopics.some(topic => topic.id === this.selected)) {
                this.selected = this.filteredTopics[0]?.id ?? '';
            }
        });
    },
    destroy() { window.removeEventListener('hashchange', this.onHashChange); },
    get filteredTopics() {
        const words = normalize(this.query).split(' ').filter(Boolean);
        return this.topics.filter(topic => words.every(word => topic.text.includes(word)));
    },
    matches(id) { return this.filteredTopics.some(topic => topic.id === id); },
    choose(id) {
        if (!this.matches(id)) return;
        this.selected = id;
        window.history.replaceState(null, '', `#${id}`);
    },
    clearSearch() {
        this.query = '';
        this.selected = this.selected || this.topics[0]?.id || '';
        this.$refs.search.focus();
    },
}));
