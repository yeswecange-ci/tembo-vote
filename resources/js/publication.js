/**
 * Parcours de publication de la photo (composant Alpine).
 *
 * Compression côté client obligatoire : redimensionnement à 1600 px max puis
 * ré-encodage JPEG qualité 0.8 → ~250 Ko. Un selfie iPhone brut pèse 4 Mo ;
 * sur une 4G moyenne c'est 30 secondes d'attente et un invité qui abandonne.
 * Le passage par le canvas supprime au passage les métadonnées EXIF, dont la
 * géolocalisation — obligatoire pour cette cible.
 */
export default function publicationPhoto() {
    return {
        // capture (choix + aperçu) → details (nom) ; la confirmation est
        // une page serveur après redirection.
        etape: 'capture',
        blob: null,
        apercu: null,
        nom: '',
        envoiEnCours: false,
        progression: 0,
        erreurs: {},
        erreurGlobale: null,

        async choisirFichier(evenement) {
            const fichier = evenement.target.files[0];
            if (!fichier) {
                return;
            }

            this.erreurGlobale = null;
            this.erreurs = {};

            try {
                this.blob = await this.compresser(fichier);
            } catch {
                this.erreurGlobale =
                    'Cette image n’a pas pu être lue. Reprenez la photo avec l’appareil photo du téléphone.';
                evenement.target.value = '';
                return;
            }

            if (this.apercu) {
                URL.revokeObjectURL(this.apercu);
            }
            this.apercu = URL.createObjectURL(this.blob);

            // Autorise à re-sélectionner le même fichier après « Reprendre »
            evenement.target.value = '';
        },

        reprendre() {
            if (this.apercu) {
                URL.revokeObjectURL(this.apercu);
            }
            this.apercu = null;
            this.blob = null;
            this.etape = 'capture';
        },

        async compresser(fichier) {
            const url = URL.createObjectURL(fichier);

            try {
                const image = new Image();
                image.src = url;
                // decode() échoue sur un format illisible (repli HEIC compris)
                await image.decode();

                const ratio = Math.min(1, 1600 / Math.max(image.naturalWidth, image.naturalHeight));
                const canvas = document.createElement('canvas');
                canvas.width = Math.max(1, Math.round(image.naturalWidth * ratio));
                canvas.height = Math.max(1, Math.round(image.naturalHeight * ratio));
                canvas.getContext('2d').drawImage(image, 0, 0, canvas.width, canvas.height);

                const blob = await new Promise((resoudre) => canvas.toBlob(resoudre, 'image/jpeg', 0.8));
                if (!blob) {
                    throw new Error('ré-encodage impossible');
                }

                return blob;
            } finally {
                URL.revokeObjectURL(url);
            }
        },

        envoyer() {
            if (this.envoiEnCours || !this.blob) {
                return;
            }

            this.envoiEnCours = true;
            this.progression = 0;
            this.erreurs = {};
            this.erreurGlobale = null;

            const donnees = new FormData();
            donnees.append('_token', document.querySelector('meta[name="csrf-token"]').content);
            donnees.append('photo', this.blob, 'photo.jpg');
            donnees.append('display_name', this.nom);

            const xhr = new XMLHttpRequest();
            xhr.open('POST', this.$root.dataset.url);
            xhr.setRequestHeader('Accept', 'application/json');
            xhr.responseType = 'json';

            // Progression réelle : un spinner indéterminé sur 4G lente donne
            // l'impression que c'est planté.
            xhr.upload.onprogress = (evenement) => {
                if (evenement.lengthComputable) {
                    this.progression = Math.round((evenement.loaded / evenement.total) * 100);
                }
            };

            xhr.onload = () => {
                if (xhr.status === 200 && xhr.response && xhr.response.redirect) {
                    window.location.assign(xhr.response.redirect);
                    return;
                }

                this.envoiEnCours = false;

                if (xhr.status === 422 && xhr.response && xhr.response.errors) {
                    this.erreurs = xhr.response.errors;
                    return;
                }

                if (xhr.status === 429) {
                    this.erreurGlobale =
                        (xhr.response && xhr.response.message) ||
                        'Trop d’envois rapprochés. Patientez une minute, puis réessayez.';
                    return;
                }

                this.erreurGlobale = 'L’envoi a échoué. Votre photo n’est pas partie — réessayez.';
            };

            // La photo compressée reste en mémoire : l'invité peut réessayer
            // sans tout recommencer, l'interface ne se vide jamais.
            xhr.onerror = () => {
                this.envoiEnCours = false;
                this.erreurGlobale =
                    'Le réseau a coupé pendant l’envoi. Votre photo n’est pas partie — vérifiez votre connexion, puis réessayez.';
            };

            xhr.send(donnees);
        },

        premiereErreur(champ) {
            return this.erreurs[champ] ? this.erreurs[champ][0] : null;
        },
    };
}
