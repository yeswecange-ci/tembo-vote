/**
 * Mode Écran — mur LED (composant Alpine).
 *
 * - Polling toutes les 2 secondes.
 * - Compteurs de votes animés sur 400 ms (animation n° 2 du projet) : la
 *   chasse fixe de JetBrains Mono empêche la largeur de sauter.
 * - Rechargement complet toutes les 30 minutes, à la seconde 0 d'une minute
 *   paire : une page ouverte 5 heures accumule les fuites mémoire.
 * - Si le polling échoue, l'écran conserve le dernier classement connu.
 *   Jamais d'erreur, jamais de page blanche devant 300 personnes.
 */
export default function ecran() {
    return {
        phase: 'setup',
        qr: '',
        top: [],
        gagnantValide: null,
        stats: { photos: 0, votes: 0 },
        horsLigne: false,
        chargeA: Date.now(),
        animations: {},

        init() {
            this.appliquer(JSON.parse(this.$el.dataset.initial), true);
            const urlApi = this.$el.dataset.urlApi;

            setInterval(async () => {
                try {
                    const reponse = await fetch(urlApi, { headers: { Accept: 'application/json' } });
                    if (!reponse.ok) {
                        return; // dernier état conservé
                    }
                    this.appliquer(await reponse.json(), false);
                    this.horsLigne = false;
                } catch {
                    this.horsLigne = true;
                }
            }, 2000);

            // Rechargement anti-fuites : après 30 min, à la seconde 0 d'une minute paire
            setInterval(() => {
                const maintenant = new Date();
                if (
                    Date.now() - this.chargeA >= 30 * 60 * 1000 &&
                    maintenant.getSeconds() === 0 &&
                    maintenant.getMinutes() % 2 === 0
                ) {
                    window.location.reload();
                }
            }, 1000);
        },

        appliquer(donnees, initiale) {
            this.phase = donnees.phase;
            // Le QR change toutes les 5 minutes : c'est le polling qui le renouvelle
            this.qr = donnees.qr;
            this.stats = donnees.stats;
            this.gagnantValide = donnees.gagnant ?? null;

            const precedents = Object.fromEntries(this.top.map((entree) => [entree.id, entree.votesAffiches]));

            this.top = donnees.top.map((entree) => ({
                ...entree,
                votesAffiches: initiale ? entree.votes : (precedents[entree.id] ?? entree.votes),
            }));

            if (!initiale) {
                this.top.forEach((entree) => this.animerCompteur(entree));
            }
        },

        /** Incrément numérique animé sur 400 ms (compteur de votes). */
        animerCompteur(entree) {
            const depart = entree.votesAffiches;
            const arrivee = entree.votes;
            if (depart === arrivee) {
                return;
            }

            cancelAnimationFrame(this.animations[entree.id]);
            const debut = performance.now();

            const pas = (instant) => {
                const progression = Math.min(1, (instant - debut) / 400);
                entree.votesAffiches = Math.round(depart + (arrivee - depart) * progression);
                if (progression < 1) {
                    this.animations[entree.id] = requestAnimationFrame(pas);
                }
            };

            this.animations[entree.id] = requestAnimationFrame(pas);
        },

        gagnant() {
            // Priorité au gagnant validé par un humain en régie
            return this.gagnantValide ?? (this.top.length ? this.top[0] : null);
        },
    };
}
