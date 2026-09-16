import { Ionicons } from "@expo/vector-icons";
import React, { useEffect, useState } from "react";
import {
    ActivityIndicator,
    Alert,
    ScrollView,
    StyleSheet,
    Text,
    TouchableOpacity,
    View,
} from "react-native";
import { THEME } from "../../constants/theme";
import { apiRequest } from "../../services/api";
import { Recette } from "../../types";

interface RecetteDetailScreenProps {
    route: any;
    navigation: any;
}

export const RecetteDetailScreen: React.FC<RecetteDetailScreenProps> = ({
    route,
    navigation,
}) => {
    const { id } = route.params;
    const [recette, setRecette] = useState<Recette | null>(null);
    const [loading, setLoading] = useState(true);

    const fetchRecette = async () => {
        try {
            const data = await apiRequest<Recette>(`/api/recettes/${id}`);
            setRecette(data);
        } catch (err: any) {
            Alert.alert(
                "Erreur",
                err.message || "Impossible de charger la recette.",
            );
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => {
        fetchRecette();
    }, [id]);

    const handleCookToday = async () => {
        try {
            await apiRequest("/api/recette_realisations", {
                method: "POST",
                body: JSON.stringify({
                    recette: `/api/recettes/${id}`,
                    realiseAt: new Date().toISOString(),
                }),
            });
            Alert.alert("Succès", "Repas enregistré dans votre timeline !");
            fetchRecette();
        } catch (err: any) {
            Alert.alert(
                "Erreur",
                err.message || "Impossible d’enregistrer le repas.",
            );
        }
    };

    if (loading) {
        return (
            <View style={styles.loadingContainer}>
                <ActivityIndicator size="large" color={THEME.colors.primary} />
            </View>
        );
    }

    if (!recette) {
        return (
            <View style={styles.emptyContainer}>
                <Text>Recette introuvable.</Text>
            </View>
        );
    }

    return (
        <ScrollView
            style={styles.container}
            contentContainerStyle={styles.content}
        >
            {/* Header Card */}
            <View style={styles.headerCard}>
                <View style={styles.emojiCircle}>
                    <Text style={{ fontSize: 36 }}>🍲</Text>
                </View>
                <Text style={styles.title}>{recette.designation}</Text>

                <TouchableOpacity
                    style={styles.cookButton}
                    onPress={handleCookToday}
                    activeOpacity={0.8}
                >
                    <Ionicons name="restaurant" size={18} color="#fff" />
                    <Text style={styles.cookButtonText}>
                        Cuisiner aujourd'hui
                    </Text>
                </TouchableOpacity>
            </View>

            {/* Ingredients section */}
            <View style={styles.sectionCard}>
                <View style={styles.sectionHeader}>
                    <Ionicons
                        name="leaf"
                        size={20}
                        color={THEME.colors.primary}
                    />
                    <Text style={styles.sectionTitle}>
                        Ingrédients ({recette.recetteIngredients?.length || 0})
                    </Text>
                </View>

                {recette.recetteIngredients &&
                recette.recetteIngredients.length > 0 ? (
                    recette.recetteIngredients.map((ri, index) => (
                        <View
                            key={ri.id}
                            style={[
                                styles.ingredientRow,
                                index ===
                                    recette.recetteIngredients!.length - 1 && {
                                    borderBottomWidth: 0,
                                },
                            ]}
                        >
                            <Text style={styles.ingredientName}>
                                {ri.ingredient?.designation || "Ingrédient"}
                            </Text>
                            {ri.quantite ? (
                                <View style={styles.quantiteBadge}>
                                    <Text style={styles.quantiteText}>
                                        {ri.quantite}
                                    </Text>
                                </View>
                            ) : null}
                        </View>
                    ))
                ) : (
                    <Text style={styles.emptySectionText}>
                        Aucun ingrédient associé.
                    </Text>
                )}
            </View>

            {/* History section */}
            <View style={styles.sectionCard}>
                <View style={styles.sectionHeader}>
                    <Ionicons
                        name="calendar"
                        size={20}
                        color={THEME.colors.primary}
                    />
                    <Text style={styles.sectionTitle}>
                        Historique des repas (
                        {recette.realisations?.length || 0})
                    </Text>
                </View>

                {recette.realisations && recette.realisations.length > 0 ? (
                    recette.realisations.map((real, index) => (
                        <View
                            key={real.id}
                            style={[
                                styles.realisationRow,
                                index === recette.realisations!.length - 1 && {
                                    borderBottomWidth: 0,
                                },
                            ]}
                        >
                            <Ionicons
                                name="checkmark-circle"
                                size={16}
                                color={THEME.colors.success}
                            />
                            <Text style={styles.realisationDate}>
                                {new Date(real.realiseAt).toLocaleDateString(
                                    "fr-FR",
                                    {
                                        weekday: "long",
                                        day: "numeric",
                                        month: "long",
                                        year: "numeric",
                                    },
                                )}
                            </Text>
                        </View>
                    ))
                ) : (
                    <Text style={styles.emptySectionText}>
                        Cette recette n'a pas encore été cuisinée.
                    </Text>
                )}
            </View>
        </ScrollView>
    );
};

const styles = StyleSheet.create({
    container: {
        flex: 1,
        backgroundColor: THEME.colors.background,
    },
    content: {
        padding: THEME.spacing.md,
        paddingBottom: 40,
    },
    headerCard: {
        backgroundColor: THEME.colors.card,
        borderRadius: THEME.borderRadius.lg,
        padding: THEME.spacing.lg,
        alignItems: "center",
        marginBottom: THEME.spacing.md,
        ...THEME.shadow,
    },
    emojiCircle: {
        width: 70,
        height: 70,
        borderRadius: 35,
        backgroundColor: THEME.colors.primaryLight,
        alignItems: "center",
        justifyContent: "center",
        marginBottom: THEME.spacing.md,
    },
    title: {
        fontSize: 22,
        fontWeight: "800",
        color: THEME.colors.text,
        textAlign: "center",
        marginBottom: THEME.spacing.md,
    },
    cookButton: {
        backgroundColor: THEME.colors.primary,
        flexDirection: "row",
        alignItems: "center",
        paddingVertical: 12,
        paddingHorizontal: 20,
        borderRadius: THEME.borderRadius.md,
        shadowColor: THEME.colors.primary,
        shadowOffset: { width: 0, height: 2 },
        shadowOpacity: 0.25,
        shadowRadius: 4,
        elevation: 3,
    },
    cookButtonText: {
        color: "#fff",
        fontWeight: "700",
        fontSize: 15,
        marginLeft: 8,
    },
    sectionCard: {
        backgroundColor: THEME.colors.card,
        borderRadius: THEME.borderRadius.lg,
        padding: THEME.spacing.md,
        marginBottom: THEME.spacing.md,
        ...THEME.shadow,
    },
    sectionHeader: {
        flexDirection: "row",
        alignItems: "center",
        marginBottom: THEME.spacing.md,
        borderBottomWidth: 1,
        borderColor: THEME.colors.border,
        paddingBottom: 8,
    },
    sectionTitle: {
        fontSize: 16,
        fontWeight: "700",
        color: THEME.colors.text,
        marginLeft: 8,
    },
    ingredientRow: {
        flexDirection: "row",
        alignItems: "center",
        justifyContent: "space-between",
        paddingVertical: 10,
        borderBottomWidth: 1,
        borderColor: THEME.colors.border,
    },
    ingredientName: {
        fontSize: 15,
        fontWeight: "500",
        color: THEME.colors.text,
    },
    quantiteBadge: {
        backgroundColor: THEME.colors.primaryLight,
        paddingVertical: 4,
        paddingHorizontal: 10,
        borderRadius: THEME.borderRadius.sm,
    },
    quantiteText: {
        fontSize: 13,
        fontWeight: "600",
        color: THEME.colors.primary,
    },
    realisationRow: {
        flexDirection: "row",
        alignItems: "center",
        paddingVertical: 10,
        borderBottomWidth: 1,
        borderColor: THEME.colors.border,
    },
    realisationDate: {
        fontSize: 14,
        color: THEME.colors.text,
        marginLeft: 8,
        textTransform: "capitalize",
    },
    emptySectionText: {
        fontSize: 13,
        color: THEME.colors.textMuted,
        fontStyle: "italic",
        paddingVertical: 8,
    },
    loadingContainer: {
        flex: 1,
        alignItems: "center",
        justifyContent: "center",
    },
    emptyContainer: {
        flex: 1,
        alignItems: "center",
        justifyContent: "center",
    },
});
