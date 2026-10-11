# MBA Shoes — Guide d'installation

Boutique WooCommerce arabe RTL avec paiement à la livraison (COD), pour le marché tunisien.
Le thème `wparabic-ecomm-cod` porte l'intégralité de la boutique : le client commande **depuis la fiche
produit**, sans panier ni tunnel de commande.

## 1. Prérequis

- Docker & Docker Compose installés
- Ports **8082**, **8081**, **3307** disponibles (configurables dans `.env`)

## 2. Démarrer l'environnement local

```bash
cd /home/vayetek/Downloads/mba-shoes
docker compose up -d
```

Attendez 30-60 secondes pour que tout démarre.

## 3. Accès

Les ports viennent de `.env` (`WP_PORT`, `PMA_PORT`, `MYSQL_PORT`).

| Service | URL | Identifiants |
|---|---|---|
| Boutique | `http://localhost:8082/` | — |
| Fiche produit | `http://localhost:8082/product/mocassin-classique/` | — |
| WordPress Admin | `http://localhost:8082/wp-admin` | créé au premier lancement |
| PHPMyAdmin | `http://localhost:8081` | `root` / `rootpass` |
| MySQL | `localhost:3307` | `mba_user` / `mba_pass`, base `mba_wp` |

## 4. Configuration WordPress

1. Ouvrir `http://localhost:8082`
2. Suivre l'assistant WordPress (langue : **العربية** — le thème est RTL)
3. Créer le compte administrateur
4. **WooCommerce est installé automatiquement** par `docker/wordpress/auto-setup.sh`
5. Assistant WooCommerce : pays **Tunisie**, devise **TND (د.ت)**
6. **Apparence → Thèmes** → activer **WPArabicEcommCOD**
7. Traductions arabes de WooCommerce :
   ```bash
   docker exec mba-wordpress wp --allow-root --path=/var/www/html language plugin install woocommerce ar
   ```

### Réglages boutique en vigueur

| Réglage | Valeur |
|---|---|
| Devise | TND, symbole `د.ت` à gauche avec espace |
| Décimales | 3 (millimes) |
| Recadrage des vignettes | 1:1 |
| Stockage des commandes | **HPOS** activé |

⚠️ **HPOS** : les commandes ne vivent plus dans `wp_posts`. `wp post list --post_type=shop_order` et
`wp post delete` **ne fonctionnent pas**. Utiliser :
```bash
docker exec mba-wordpress wp --allow-root --path=/var/www/html wc shop_order list --user=1
docker exec mba-wordpress wp --allow-root --path=/var/www/html wc shop_order delete <id> --user=1 --force=true
```

## 5. Le produit : UN SEUL produit variable

> **⚠️ Ne pas créer un produit par couleur.** La boutique repose sur **un seul** produit portant
> **deux** attributs de variation, `اللون` × `القياس`. Une ancienne version de ce guide faisait créer deux
> produits séparés — c'est obsolète et ça casse le sélecteur de couleur.

### 5.1 Créer les attributs

**Produits → Attributs**, créer les deux :

| Attribut | Slug | Termes |
|---|---|---|
| `اللون` | `color` → taxonomie `pa_color` | `أسود` (slug `noir`), `مرون` (slug `marron`) |
| `القياس` | `size` → taxonomie `pa_size` | `40`, `41`, `42`, `43`, `44` |

Sur chaque terme de **اللون**, le thème ajoute un champ **couleur (Hex)** : ouvrir
**Produits → Attributs → اللون → Configurer les termes**, modifier le terme et choisir la teinte
(`#1c1c1c` pour أسود, `#7a4a21` pour مرون). Ces teintes deviennent les pastilles cliquables sous la liste
déroulante sur la fiche produit. Un attribut dont **un seul** terme n'a pas de teinte reste en liste
déroulante simple — c'est voulu, `القياس` n'a donc pas de pastilles.

### 5.2 Créer le produit

- **Produits → Ajouter**
- Nom : `موكاسان كلاسيكي` — SKU : `MBA-MOC`
- Type : **Produit variable**
- **Attributs** : ajouter `اللون` puis `القياس`, cocher **« Utilisé pour les variations »** sur les deux
- **Image produit** : la photo noire — **Galerie produit** : la photo marron
  (une photo par couleur dans la galerie : c'est ce qui fait *glisser* la galerie au lieu de remplacer
  l'image, voir 5.4)
- **Variations → Générer les variations** → 2 × 5 = **10 variations**
- **Valeurs par défaut du formulaire** : `أسود` / `42`

### 5.3 Prix et image sur chaque variation

Déplier chaque variation et renseigner :

| Champ | Valeur |
|---|---|
| Prix normal | `49` |
| Image | la photo **de sa couleur** (noire pour les 5 `أسود`, marron pour les 5 `مرون`) |

> **⚠️ Le piège :** le bouton **« Générer les variations »** recrée les variations à zéro — nouveaux IDs,
> **prix et images perdus**. Après chaque génération il faut reposer les 10 prix et les 10 images.
> Symptômes si on l'oublie : bouton de commande grisé (pas de prix → produit non achetable), et la photo
> qui ne change plus quand on choisit une couleur (chaque variation retombe alors sur l'image du produit
> parent).

Contrôle rapide en ligne de commande :

```bash
docker exec mba-wordpress wp --allow-root --path=/var/www/html eval '
$p = wc_get_product(10);
foreach ($p->get_children() as $id) {
  $v = wc_get_product($id); $a = $v->get_attributes();
  printf("%-6d %-8s %-4s prix=%-6s image=%s\n", $id, $a["pa_color"]??"?", $a["pa_size"]??"?",
    $v->get_regular_price() ?: "VIDE", $v->get_image_id("edit") ?: "VIDE");
}'
```

Aucune ligne ne doit afficher `VIDE`.

### 5.4 Changement de photo selon la couleur

WooCommerce s'en charge nativement, sans code. Sa règle :

- si la photo de la variation **fait partie de la galerie** → la galerie **glisse** jusqu'à elle,
  les miniatures restent visibles ;
- sinon → l'image principale est **remplacée** sur place, sans miniatures.

D'où la consigne de 5.2 : mettre une photo de chaque couleur dans la galerie.

## 6. Livraison et gouvernorats

Le formulaire COD calcule les frais par gouvernorat (5 à 12 DT) et propose la délégation correspondante.
Les données sont dans le thème, pas en base :

- `inc/cod-form.php` → `wparabic_get_tunisian_governorates()` et `wparabic_get_shipping_cost()`
- `inc/tn-delegations.php` → 24 gouvernorats, 266 délégations

Pour ajuster les tarifs sans modifier le thème, utiliser le filtre `wparabic_shipping_costs`.

## 7. Mode « bientôt disponible »

Si la boutique renvoie une page vide aux visiteurs alors qu'elle s'affiche pour vous, c'est ce mode :
**WooCommerce → Réglages → Général → Mode « bientôt disponible »**. Les administrateurs le contournent, pas
les visiteurs. En base : `woocommerce_coming_soon` et `woocommerce_store_pages_only`.

## 8. Tester une commande

1. Ouvrir `http://localhost:8082/product/mocassin-classique/`
2. Choisir une couleur et une pointure — la photo doit suivre la couleur
3. Remplir nom, téléphone, gouvernorat, délégation
4. Cliquer **👈 انقر هنا لتأكيد الطلب**
5. Vérifier dans **WooCommerce → Commandes** : statut `processing`, paiement `cod`, la bonne variation
   (`pa_color` + `pa_size`) et un frais `التوصيل` correspondant au gouvernorat

## 8 bis. E-mails en développement (Mailtrap)

En local, tous les e-mails de WordPress et WooCommerce (nouvelle commande, confirmation client…) partent
par SMTP vers la boîte de test **Mailtrap** : ils y sont capturés, rien n'est réellement envoyé.

- Identifiants : variables `MAIL_*` du fichier `.env` (non versionné), transmises au conteneur par
  `docker-compose.yml`.
- Envoi : `wp-content/mu-plugins/smtp-from-env.php`. Sans variables `MAIL_*`, il ne fait rien.
- Expéditeur : `MAIL_FROM_ADDRESS` si défini, sinon l'e-mail d'administration du site.
- Après une modification de `.env` : `docker compose up -d wordpress` pour recharger les variables.

Test rapide :
```bash
docker exec mba-wordpress wp --allow-root --path=/var/www/html eval 'var_dump(wp_mail("test@example.com", "Test", "OK"));'
```

En production, renseigner les mêmes variables `MAIL_*` avec un vrai fournisseur SMTP (Brevo, Mailgun…),
dans `.env` et dans la section `environment` du service `wordpress` de `docker-compose.prod.yml`.

## 9. Commandes Docker utiles

```bash
docker compose up -d           # Démarrer
docker compose down            # Arrêter
docker compose logs wordpress  # Voir les logs
docker compose restart         # Redémarrer
docker compose down -v         # Supprimer TOUT (base incluse !)
```

Sauvegarde de la base :
```bash
docker exec mba-mysql sh -c 'mysqldump -u root -prootpass --ssl-mode=DISABLED mba_wp' > sauvegarde.sql
```

## 10. Landing page statique (hérité)

Le dépôt contient encore l'ancienne page de vente autonome, antérieure au thème WordPress :
`mba-landing-page.html`, `confirmation.html` et le relais `api/create-order.php`.

**Elle n'est plus déployée** : `docker-compose.prod.yml` ne monte plus que `wp-content`. Un `index.html` à
la racine masquait la page d'accueil WordPress. Les clés `WC_CONSUMER_KEY` / `WC_CONSUMER_SECRET` ont été
retirées de `.env` et `.env.prod`.

**La boutique n'en dépend pas** : le formulaire COD du thème crée la commande directement en PHP.
La page passait par l'API REST WooCommerce avec une table d'IDs de variations codée en dur dans
`api/create-order.php`, qui référence les deux anciens produits et n'est **plus valide**. Pour la remettre
en service, il faudrait regénérer des clés API (**WooCommerce → Réglages → Avancé → API REST**), les
remettre dans `.env`, réécrire cette table et rétablir les montages.

---

# Déploiement VPS

## 1. Préparer le VPS

```bash
curl -fsSL https://get.docker.com | sh
sudo apt install docker-compose-plugin -y
```

## 2. Copier les fichiers

```bash
scp -r /home/vayetek/Downloads/mba-shoes user@vps-ip:/var/www/mba-shoes/
```

## 3. Configurer l'environnement de production

```bash
cd /var/www/mba-shoes
cp .env.prod .env
nano .env        # changer TOUS les mots de passe
```

## 4. Démarrer en production

```bash
docker compose -f docker-compose.prod.yml up -d
```

## 5. Configurer le domaine

- Pointer l'enregistrement DNS A vers l'IP du VPS
- Dans wp-admin : **Réglages → Général** → mettre à jour l'adresse du site
- Penser à désactiver le mode « bientôt disponible » (§7) avant l'ouverture

## 6. SSL

```bash
sudo apt install certbot -y
# Installer et configurer Let's Encrypt
```
