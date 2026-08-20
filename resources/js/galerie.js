/**
 * Galerie et vote (composant Alpine).
 *
 * - Polling toutes les 3 s avec ETag : si rien n'a changé, le serveur répond
 *   304 et aucun corps n'est retransmis.
 * - Défilement infini par curseur (created_at + id).
 * - Autant de votes que l'invité veut, un par photo, jamais pour la sienne ;
 *   un second appui retire le vote. Mise à jour optimiste avec retour arrière
 *   si le serveur refuse.
 * - Si le réseau tombe, l'interface garde le dernier état connu et signale
 *   discrètement la reconnexion. Elle ne se vide jamais.
 */
export default function galerie() {
    return {
        photos: [],
        mesVotes: [],
        maPhotoId: null,
        // Appuis en cours, par photo : sur 4G, l'invité tape deux fois avant la
        // première réponse et annulerait sans le savoir son propre vote.
        enVol: {},
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
            this.mesVotes = initial.mesVotes;
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

                // La phase voyage avec la galerie : le vote se ferme ici, en
                // 3 secondes, sans que l'invité ait à recharger la page.
                if (typeof donnees.peutVoter === 'boolean') {
                    this.peutVoter = donnees.peutVoter;
                }

                if (Array.isArray(donnees.retirees) && donnees.retirees.length) {
                    this.retirer(donnees.retirees);
                }

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
         * Une photo retirée quitte la grille sans attendre un rechargement : le
         * polling n'ajoute pas seulement, il retire aussi. Si c'était la photo
         * votée, le serveur a déjà libéré le vote — l'invité doit le savoir
         * plutôt que de croire avoir voté.
         */
        retirer(retirees) {
            const sorties = new Set(retirees);
            const restantes = this.photos.filter((photo) => !sorties.has(photo.id));

            // Jamais de réaffectation sans raison : la liste arrive à chaque
            // sondage et la grille se redessinerait toutes les 3 secondes.
            if (restantes.length !== this.photos.length) {
                this.photos = restantes;
            }

            // Un vote peut porter sur une photo absente de la grille chargée :
            // ce test ne dépend pas de ce qui vient d'être retiré à l'écran.
            const votesPerdus = this.mesVotes.filter((id) => sorties.has(id));

            if (votesPerdus.length) {
                this.mesVotes = this.mesVotes.filter((id) => !sorties.has(id));
                this.erreurGlobale = votesPerdus.length > 1
                    ? votesPerdus.length + ' photos que vous aviez choisies ont été retirées de la galerie : vos votes sont partis avec elles.'
                    : 'Une photo que vous aviez choisie a été retirée de la galerie : votre vote est parti avec elle.';
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

        estVote(photoId) {
            return this.mesVotes.includes(photoId);
        },

        /**
         * Un appui = un vote, un second appui le retire. Sa propre photo n'est
         * jamais votable. Optimiste, avec retour arrière si le serveur refuse.
         */
        async voter(photoId) {
            if (!this.peutVoter || photoId === this.maPhotoId || this.enVol[photoId]) {
                return;
            }

            const etaitVotee = this.estVote(photoId);
            this.enVol[photoId] = true;
            this.appliquerVote(photoId, !etaitVotee);
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
                    // Le serveur fait foi : il a pu retirer là où le client
                    // croyait ajouter (deux onglets ouverts, un appui perdu).
                    const donnees = await reponse.json().catch(() => null);
                    if (donnees && typeof donnees.vote === 'boolean') {
                        this.appliquerVote(photoId, donnees.vote);
                    }

                    this.confirmerVote(photoId);
                    return;
                }

                this.appliquerVote(photoId, etaitVotee);

                if (reponse.status === 401) {
                    window.location.assign('/tembo');
                    return;
                }

                const donnees = await reponse.json().catch(() => null);
                this.erreurGlobale =
                    (donnees && (donnees.message || (donnees.errors && donnees.errors.photo_id && donnees.errors.photo_id[0]))) ||
                    'Le vote n’a pas été pris en compte. Réessayez.';
            } catch {
                this.appliquerVote(photoId, etaitVotee);
                this.erreurGlobale = 'Le réseau a coupé : votre vote n’a pas été pris en compte. Réessayez.';
            } finally {
                delete this.enVol[photoId];
            }
        },

        /** Ajoute ou retire la photo de mes votes, sans jamais de doublon. */
        appliquerVote(photoId, votee) {
            const sansCettePhoto = this.mesVotes.filter((id) => id !== photoId);
            this.mesVotes = votee ? [...sansCettePhoto, photoId] : sansCettePhoto;
        },

        /**
         * Confirmation explicite après chaque appui : message nominatif
         * (enregistré ou retiré) pendant 2,5 s + légère vibration.
         * Aucun écran en plus, aucune action à faire.
         */
        confirmerVote(photoId) {
            if (navigator.vibrate) {
                navigator.vibrate(30);
            }

            const nom = (this.photos.find((photo) => photo.id === photoId) || {}).nom;
            this.messageVote = this.estVote(photoId)
                ? 'Vote enregistré pour ' + nom + ' ✓'
                : 'Vote retiré pour ' + nom;

            this.confirmationVote = true;
            clearTimeout(this.minuterieConfirmation);
            this.minuterieConfirmation = setTimeout(() => {
                this.confirmationVote = false;
                this.messageVote = null;
            }, 2500);
        },

        /** Consigne d'en-tête : suit la phase reçue au polling. */
        texteEntete() {
            return this.peutVoter
                ? 'Touchez toutes les photos qui vous plaisent. Un appui de plus retire le vote.'
                : 'Les votes sont fermés pour le moment.';
        },

        /** Ligne principale de la barre fixe : mes votes, ou la consigne du moment. */
        texteBarre() {
            if (this.mesVotes.length) {
                return this.mesVotes.length > 1 ? 'photos choisies' : 'photo choisie';
            }

            return this.peutVoter ? 'Touchez les photos qui vous plaisent' : 'Votes fermés';
        },
    };
}
