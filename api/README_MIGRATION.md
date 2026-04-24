# Migration du compteur journalier vers MySQL

## ✅ Fichiers créés/modifiés

### Nouveaux fichiers
- `api/config.php` - Configuration de connexion MySQL
- `api/compteur.php` - API REST pour le compteur journalier
- `api/init-database.sql` - Script d'initialisation des tables

### Fichiers modifiés
- `index.html` - Remplacement localStorage par appels API MySQL

## 📋 Étapes d'installation sur Hostinger

### 1. Créer les tables MySQL

1. Connectez-vous à **phpMyAdmin** sur Hostinger
2. Sélectionnez la base de données `u411940699_Trackship`
3. Cliquez sur l'onglet **SQL**
4. Copiez/collez le contenu complet du fichier `api/init-database.sql`
5. Cliquez sur **Exécuter**

✅ Vous devriez voir un message de succès et 3 tables créées :
- `compteur_jours`
- `bateaux_vus`
- `bateaux_zone_rouge_actifs`

### 2. Vérifier les données initiales

Exécutez cette requête dans phpMyAdmin pour vérifier :

```sql
SELECT * FROM compteur_jours ORDER BY numero_jour;
```

Vous devriez voir :
```
numero_jour | date_jour  | compteur_passages
------------|------------|------------------
1           | 2025-12-04 | 8
2           | 2025-12-05 | 6
```

### 3. Pousser le code sur Git

Le webhook Git automatique va déployer les fichiers sur Hostinger.

```bash
git add api/config.php api/compteur.php api/init-database.sql index.html
git commit -m "Migration compteur vers MySQL Hostinger"
git push
```

### 4. Tester la synchronisation

1. Ouvrez le site sur **ordinateur 1** : https://trackship.bakabi.fr
2. Vérifiez que le compteur affiche :
   - Jour 2 (Aujourd'hui) : 6 passages
   - Jour 1 (dans l'historique) : 8 passages

3. Ouvrez le site sur **ordinateur 2**
4. Vérifiez que les mêmes chiffres s'affichent

5. Faites entrer un bateau en zone rouge (≤1km) sur **ordinateur 1**
6. Attendez 10 secondes
7. Vérifiez sur **ordinateur 2** que le compteur a augmenté

## 🔧 Endpoints API

L'API est accessible à : `https://trackship.bakabi.fr/api/compteur.php`

### GET - Récupérer le jour actuel
```
GET /api/compteur.php?action=get_current
```

### GET - Récupérer l'historique
```
GET /api/compteur.php?action=get_history
```

### POST - Incrémenter le compteur
```
POST /api/compteur.php?action=increment
Body: {trackId: "123456", shipName: "Le Bateau"}
```

### POST - Mettre à jour les bateaux en zone rouge
```
POST /api/compteur.php?action=update_zone_rouge
Body: {trackIds: ["123", "456", "789"]}
```

### POST - Effacer des jours
```
POST /api/compteur.php?action=delete_days
Body: {type: "all"} ou {type: "range", debut: 1, fin: 30} ou {type: "single", jour: 15}
```

## 🐛 Dépannage

### Erreur de connexion MySQL

Si vous voyez `❌ Erreur connexion API compteur` dans la console :

1. Vérifiez que les identifiants dans `api/config.php` sont corrects
2. Vérifiez que les tables existent dans phpMyAdmin
3. Vérifiez les logs d'erreur PHP sur Hostinger

### Le compteur ne s'affiche pas

1. Ouvrez la console navigateur (F12)
2. Vérifiez s'il y a des erreurs JavaScript
3. Vérifiez que l'API répond : ouvrez `https://trackship.bakabi.fr/api/compteur.php?action=get_current`

### Les 2 ordinateurs ne sont pas synchronisés

1. Vérifiez que les 2 ordinateurs accèdent au même site (trackship.bakabi.fr)
2. Faites Ctrl+F5 pour recharger le cache
3. Attendez le prochain refresh (10 secondes ou 2 secondes en zone rouge)

## ✨ Avantages de cette migration

✅ **Compteur partagé** entre tous les utilisateurs
✅ **Aucun doublon** même si 2 personnes surveillent en même temps
✅ **Données persistantes** dans MySQL Hostinger
✅ **Historique illimité** avec effacement sélectif
✅ **Synchronisation automatique** toutes les 10 secondes
✅ **Changement de jour automatique** à minuit

## 📊 Structure des tables

### compteur_jours
Stocke les jours de comptage et le total de passages par jour.

### bateaux_vus
Historique permanent de tous les bateaux détectés en zone rouge.

### bateaux_zone_rouge_actifs
État temps réel des bateaux actuellement en zone rouge (≤1km).
Cette table est nettoyée automatiquement lors du changement de jour.
