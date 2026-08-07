import Alpine from 'alpinejs';
import ecran from './ecran';
import galerie from './galerie';
import publicationPhoto from './publication';
import moderationEtat from './regie';

// Alpine garde l'état côté client et survit à une requête perdue :
// c'est le seul framework front du projet (contrainte du brief).
window.Alpine = Alpine;

Alpine.data('publicationPhoto', publicationPhoto);
Alpine.data('moderationEtat', moderationEtat);
Alpine.data('galerie', galerie);
Alpine.data('ecran', ecran);

Alpine.start();
