/**
 * File de modération (composant Alpine) : rafraîchissement automatique
 * toutes les 5 secondes. En cas d'échec réseau, l'interface garde le
 * dernier état connu et signale discrètement la reconnexion.
 */
export default function moderationEtat() {
    return {
        enAttente: 0,
        photoId: null,
        url: null,
        horsLigne: false,

        init() {
            this.enAttente = Number(this.$el.dataset.enAttente || 0);
            this.photoId = this.$el.dataset.photoId || null;
            this.url = this.$el.dataset.url;

            setInterval(() => this.rafraichir(), 5000);
        },

        async rafraichir() {
            try {
                const reponse = await fetch(this.url + (this.photoId ? '?photo=' + this.photoId : ''), {
                    headers: { Accept: 'application/json' },
                });
                if (!reponse.ok) {
                    return;
                }
                const etat = await reponse.json();
                this.horsLigne = false;
                this.enAttente = etat.pending;

                // La photo affichée a été traitée par l'autre modérateur, ou une
                // première photo vient d'arriver dans une file vide → on recharge
                if ((this.photoId && etat.currentStillPending === false) || (!this.photoId && etat.pending > 0)) {
                    window.location.reload();
                }
            } catch {
                this.horsLigne = true;
            }
        },
    };
}
