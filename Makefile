# Raccourcis de développement GPV.
.DEFAULT_GOAL := help
.PHONY: help install db test build dev-api dev-web keys lint

help: ## Affiche cette aide
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | awk 'BEGIN{FS=":.*?## "};{printf "  \033[36m%-12s\033[0m %s\n",$$1,$$2}'

install: ## Installe les dépendances PHP et JavaScript
	cd backend && composer install
	cd frontend && npm install

keys: ## Génère la paire de clés JWT (à faire une fois)
	cd backend && php bin/console lexik:jwt:generate-keypair --skip-if-exists

db: ## Recrée la base et charge le compte de démonstration
	cd backend && composer db:reset

test: ## Lance les tests de l'API
	cd backend && php vendor/bin/phpunit

lint: ## Vérifie les types du front
	cd frontend && npm run lint

build: ## Construit le front pour la production
	cd frontend && npm run build

dev-api: ## Démarre l'API sur le port 8000
	cd backend && php -S 127.0.0.1:8000 -t public

dev-web: ## Démarre le front sur le port 5173
	cd frontend && npm run dev
