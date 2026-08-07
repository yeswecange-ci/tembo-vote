/**
 * Galerie et vote (composant Alpine).
 *
 * - Polling toutes les 3 s avec ETag : si rien n'a changé, le serveur répond
 *   304 et aucun corps n'est retransmis.
 * - Défilement infini par curseur (created_at + id).
 * - Un seul vote actif, changeable d'un appui ; mise à jour optimiste avec
 *   retour arrière si le serveur refuse.
 * - Si le réseau tombe, l'interface garde le dernier état connu et signale
 *   discrètement la reconnexion. Elle ne se vide jamais.
 */
export default function galerie() {
    return {
        photos: [],
        monVote: null,
        maPhotoId: null,
        peutVoter: false,
        complet: true,
        horsLigne: false,
        chargementAncien: false,
        erreurGlobale: null,
        etag: null,
        nouveaux: {},
        confirmationVote: false,
        messageVote: null,
        minuterieConfirmation: null,
        recherche: '',
        catalogueComplet: false,

        urlApi: null,
        urlVote: null,

        init() {
            const initial = JSON.parse(this.$el.dataset.initial);
            this.photos = initial.photos;
            this.monVote = initial.monVote;
            this.maPhotoId = initial.maPhotoId;
            this.peutVoter = initial.peutVoter;
            this.complet = initial.complet;
            this.urlApi = this.$el.dataset.urlApi;
            this.urlVote = this.$el.dataset.urlVote;

            setInterval(() => this.sonder(), 3000);

            // Chargement des pages précédentes au défilement
            const sentinelle = this.$refs.sentinelle;
            if (sentinelle) {
                new IntersectionObserver((entrees) => {
                    if (entrees[0].isIntersecting) {
                        this.chargerAncien();
                    }
                }).observe(sentinelle);
            }
        },

        curseurRecent() {
            return this.photos.length ? this.photos[0].curseur : '';
        },

        curseurAncien() {
            return this.photos.length ? this.photos[this.photos.length - 1].curseur : '';
        },

        /** Polling : uniquement les photos plus récentes que la première affichée. */
        async sonder() {
            try {
                const entetes = { Accept: 'application/json' };
                if (this.etag) {
                    entetes['If-None-Match'] = this.etag;
                }

                const reponse = await fetch(this.urlApi + '?apres=' + encodeURIComponent(this.curseurRecent()), {
                    headers: entetes,
                });

                if (reponse.status === 304) {
                    this.horsLigne = false;
                    return;
                }
                if (reponse.status === 401) {
                    // Session expirée : retour à l'écran du code
                    window.location.assign('/tembo');
                    return;
                }
                if (!reponse.ok) {
                    return; // on garde le dernier état connu
                }

                this.etag = reponse.headers.get('ETag');
                const donnees = await reponse.json();
                this.horsLigne = false;

                if (donnees.photos.length) {
                    const connus = new Set(this.photos.map((photo) => photo.id));
                    const fraiches = donnees.photos.filter((photo) => !connus.has(photo.id));
                    fraiches.forEach((photo) => (this.nouveaux[photo.id] = true));
                    this.photos = [...fraiches, ...this.photos];
                }
            } catch {
                this.horsLigne = true;
            }
        },

        /**
         * Recherche par prénom : filtre côté client. Au premier caractère
         * saisi, la liste complète est chargée (une seule fois, servie par
         * le cache serveur) pour que la recherche couvre toute la galerie.
         */
        photosVisibles() {
            const terme = this.recherche.trim().toLowerCase();
            if (!terme) {
                return this.photos;
            }

            return this.photos.filter((photo) => photo.nom.toLowerCase().includes(terme));
        },

        async chargerCatalogue() {
            if (this.catalogueComplet || !this.recherche.trim()) {
                return;
            }
            this.catalogueComplet = true;

            try {
                const reponse = await fetch(this.urlApi + '?tout=1', { headers: { Accept: 'application/json' } });
                if (!reponse.ok) {
                    this.catalogueComplet = false;
                    return;
                }
                const donnees = await reponse.json();
                const connus = new Set(this.photos.map((photo) => photo.id));
                this.photos = [...this.photos, ...donnees.photos.filter((photo) => !connus.has(photo.id))];
                // Le curseur (date + id) est lexicographiquement croissant :
                // tri décroissant = plus récentes d'abord, comme la grille
                this.photos.sort((a, b) => b.curseur.localeCompare(a.curseur));
                this.complet = true;
            } catch {
                this.catalogueComplet = false;
                this.horsLigne = true;
            }
        },

        /** Défilement infini : la page de photos précédant la plus ancienne affichée. */
        async chargerAncien() {
            if (this.chargementAncien || this.complet || !this.photos.length) {
                return;
            }
            this.chargementAncien = true;

            try {
                const reponse = await fetch(this.urlApi + '?avant=' + encodeURIComponent(this.curseurAncien()), {
                    headers: { Accept: 'application/json' },
                });
                if (!reponse.ok) {
                    return;
                }
                const donnees = await reponse.json();
                const connus = new Set(this.photos.map((photo) => photo.id));
                this.photos = [...this.photos, ...donnees.photos.filter((photo) => !connus.has(photo.id))];
                this.complet = donnees.complet;
                this.horsLigne = false;
            } catch {
                this.horsLigne = true;
            } finally {
                this.chargementAncien = false;
            }
        },

        /** Un appui sur une photo = un vote. Optimiste, avec retour arrière. */
        async voter(photoId) {
            if (!this.peutVoter || this.monVote === photoId) {
                return;
            }

            const votePrecedent = this.monVote;
            this.monVote = photoId;
            this.erreurGlobale = null;

            try {
                const reponse = await fetch(this.urlVote, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    },
                    body: JSON.stringify({ photo_id: photoId }),
                });

                if (reponse.ok) {
                    this.confirmerVote(photoId, votePrecedent);
                    return;
                }

                this.monVote = votePrecedent;

                if (reponse.status === 401) {
                    window.location.assign('/tembo');
                    return;
                }

                const donnees = await reponse.json().catch(() => null);
                this.erreurGlobale =
                    (donnees && (donnees.message || (donnees.errors && donnees.errors.photo_id && donnees.errors.photo_id[0]))) ||
                    'Le vote n’a pas été pris en compte. Réessayez.';
            } catch {
                this.monVote = votePrecedent;
                this.erreurGlobale = 'Le réseau a coupé : votre vote n’a pas été pris en compte. Réessayez.';
            }
        },

        /**
         * Confirmation explicite après chaque vote : message nominatif
         * (enregistré ou transféré) pendant 2,5 s + légère vibration.
         * Aucun écran en plus, aucune action à faire.
         */
        confirmerVote(photoId, votePrecedent) {
            if (navigator.vibrate) {
                navigator.vibrate(30);
            }

            const nom = (this.photos.find((photo) => photo.id === photoId) || {}).nom;
            this.messageVote = votePrecedent
                ? 'Vote transféré à ' + nom + ' ✓'
                : 'Vote enregistré pour ' + nom + ' ✓';

            this.confirmationVote = true;
            clearTimeout(this.minuterieConfirmation);
            this.minuterieConfirmation = setTimeout(() => {
                this.confirmationVote = false;
                this.messageVote = null;
            }, 2500);
        },

        nomDeMonVote() {
            const photo = this.photos.find((candidate) => candidate.id === this.monVote);
            return photo ? photo.nom : null;
        },
    };
}
