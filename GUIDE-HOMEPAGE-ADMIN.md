# Guide admin — Personnaliser la page d'accueil

Le thème **WPArabicEcommCOD** permet de composer la page d'accueil sans toucher au code :
sections réordonnables, masquables, dupliquables, avec textes traduits par langue.
Tout se fait depuis l'administration WordPress (interface en arabe).

> Système vérifié en conditions réelles le 2026-10-11 (test d'activation/désactivation
> d'une section via le Customizer, publication, contrôle sur le site public, restauration).

## 1. Accéder à la personnalisation

**Voie recommandée — avec aperçu live :**

**Apparence (المظهر) → الصفحة الرئيسية**

Ce raccourci ouvre le Customizer directement sur la zone des sections de la page
d'accueil, avec le site affiché en aperçu à côté. Chaque modification se voit en direct.

![Le personnalisateur ouvert sur les sections de la page d'accueil](docs/homepage-customizer.png)

**Voie classique — écran complet :**

**Apparence (المظهر) → ودجات (Widgets)** puis zone **« أقسام الصفحة الرئيسية »**
(sections de la page d'accueil). L'écran est resté volontairement en mode « classique »
(glisser-déposer), pas en éditeur de blocs.

![L'écran Widgets avec la zone des sections de la page d'accueil](docs/homepage-widgets.png)

## 2. Les 4 types de sections

Dans l'admin, les sections du thème sont préfixées **« خيارات القالب »** (options du thème) :

| Widget | Rendu sur le site | Réglages principaux |
|---|---|---|
| **لافتة ترويجية** — bannière | Grande bannière promo en haut | image de fond, titre, sous-titre, texte + lien du bouton, hauteur (normale/haute) |
| **قسم التصنيفات** — catégories | Tuiles de catégories de produits | 4 maquettes : mosaïque, grille de cartes, cercles, carrousel ; nombre de catégories, colonnes, choix des catégories |
| **قسم المنتجات** — produits | Grille de produits | nombre (1–24), colonnes (4/5), tri (date/popularité/aléatoire), filtre par catégorie |
| **قسم المميزات** — atouts | 4 blocs « icône + titre + texte » | 4 icônes FontAwesome + textes |

## 3. Les opérations

- **Réordonner** : glisser-déposer la section dans la liste. L'ordre affiché = l'ordre
  sur le site. *(Dans le Customizer, on peut aussi faire Maj + clic sur une section
  directement dans l'aperçu pour l'éditer.)*
- **Masquer sans perdre les réglages** : décocher **« إظهار هذا القسم في الصفحة الرئيسية »**
  (afficher cette section) dans le formulaire du widget.
- **Ajouter une section** : depuis la liste des widgets disponibles (ودجات متوفرة),
  glisser un des 4 widgets du thème dans la zone أقسام الصفحة الرئيسية.
- **Dupliquer** : ajouter une seconde instance du même type — par exemple deux grilles
  de produits (nouveautés + promotions).
- **Supprimer** : ouvrir le widget puis lien **« حذف »** (supprimer).
- **Publier** : bouton **« نشر »** en haut du Customizer (dans l'écran Widgets,
  l'enregistrement est automatique).
- **Traduire les textes** : chaque widget contient un bloc **« الترجمات »** pour saisir
  les variantes par langue (les visiteurs voient la version de leur langue).

## 4. Contenu libre au-dessus des sections

La page définie comme accueil (**Pages → الرئيسية**) affiche son contenu **au-dessus**
des sections, dans la même grille — pratique pour un bloc d'annonce rédigé dans
l'éditeur. Laisser la page vide pour ne rien afficher.

## 5. Filet de sécurité

Si la zone est entièrement vidée, le thème affiche automatiquement les trois sections
d'origine (produits, atouts, catégories) : **la page d'accueil n'est jamais vide**.

---

## Annexe — état du site au 2026-10-11

Configuration actuelle de la zone (ordre et visibilité) :

| # | Section | État |
|---|---|---|
| 1 | Bannière (لافتة ترويجية) | **masquée** (case décochée) |
| 2 | Catégories (قسم التصنيفات) | visible |
| 3 | Produits (قسم المنتجات) | visible — 8 produits, 5 colonnes |
| 4 | Atouts (قسم المميزات) | visible |

![Rendu actuel de la page d'accueil](docs/homepage-front.png)

Deux détails repérés pendant la vérification, à corriger si souhaité :

1. Le titre de la section catégories commence par un **« ! » parasite** :
   « !أقسام المتجر » — visible tel quel sur le site. Le retirer : Customizer →
   section التصنيفات → champ العنوان.
2. Le bouton de la bannière pointe vers `http://localhost:8082/shop/` (URL locale) —
   à remplacer par l'URL publique de la boutique si la bannière est réactivée.
