export interface User {
    id: number;
    email: string;
    prenom?: string | null;
    nom?: string | null;
}

export interface Ingredient {
    id: number;
    designation: string;
    createdAt?: string;
    updatedAt?: string;
}

export interface RecetteIngredient {
    id: number;
    quantite?: string | null;
    ingredient: Ingredient;
}

export interface RecetteRealisation {
    id: number;
    realiseAt: string;
    recette?: {
        id: number;
        designation: string;
    };
}

export interface Recette {
    id: number;
    designation: string;
    createdAt?: string;
    updatedAt?: string;
    recetteIngredients?: RecetteIngredient[];
    realisations?: RecetteRealisation[];
}

export interface AuthResponse {
    token: string;
    user: User;
}
