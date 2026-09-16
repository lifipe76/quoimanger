import { Ionicons } from "@expo/vector-icons";
import React, { useCallback, useEffect, useState } from "react";
import {
    ActivityIndicator,
    Alert,
    FlatList,
    RefreshControl,
    StyleSheet,
    Text,
    TextInput,
    TouchableOpacity,
    View,
} from "react-native";
import { THEME } from "../../constants/theme";
import { apiRequest } from "../../services/api";
import { Recette } from "../../types";

interface RecettesScreenProps {
    navigation: any;
}

export const RecettesScreen: React.FC<RecettesScreenProps> = ({
    navigation,
}) => {
    const [recettes, setRecettes] = useState<Recette[]>([]);
    const [loading, setLoading] = useState(true);
    const [refreshing, setRefreshing] = useState(false);
    const [search, setSearch] = useState("");

    const fetchRecettes = async () => {
        try {
            const data = await apiRequest<any>("/api/recettes");
            const list: Recette[] = Array.isArray(data)
                ? data
                : data?.["hydra:member"] || data?.member || [];
            setRecettes(list);
        } catch (err: any) {
            Alert.alert(
                "Erreur",
                err.message || "Impossible de charger les recettes.",
            );
        } finally {
            setLoading(false);
            setRefreshing(false);
        }
    };

    useEffect(() => {
        const unsubscribe = navigation.addListener("focus", () => {
            fetchRecettes();
        });
        return unsubscribe;
    }, [navigation]);

    const onRefresh = useCallback(() => {
        setRefreshing(true);
        fetchRecettes();
    }, []);

    const handleDelete = (item: Recette) => {
        Alert.alert(
            "Supprimer la recette",
            `Êtes-vous sûr de vouloir supprimer "${item.designation}" ?`,
            [
                { text: "Annuler", style: "cancel" },
                {
                    text: "Supprimer",
                    style: "destructive",
                    onPress: async () => {
                        try {
                            await apiRequest(`/api/recettes/${item.id}`, {
                                method: "DELETE",
                            });
                            setRecettes((prev) =>
                                prev.filter((r) => r.id !== item.id),
                            );
                        } catch (err: any) {
                            Alert.alert(
                                "Erreur",
                                err.message ||
                                    "Impossible de supprimer la recette.",
                            );
                        }
                    },
                },
            ],
        );
    };

    const filteredRecettes = recettes.filter((r) =>
        r.designation.toLowerCase().includes(search.toLowerCase()),
    );

    return (
        <View style={styles.container}>
            {/* Header actions: Search + Add Button */}
            <View style={styles.topContainer}>
                <View style={styles.searchBar}>
                    <Ionicons
                        name="search"
                        size={18}
                        color={THEME.colors.textMuted}
                    />
                    <TextInput
                        style={styles.searchInput}
                        placeholder="Rechercher une recette..."
                        placeholderTextColor={THEME.colors.textMuted}
                        value={search}
                        onChangeText={setSearch}
                    />
                    {search.length > 0 && (
                        <TouchableOpacity onPress={() => setSearch("")}>
                            <Ionicons
                                name="close-circle"
                                size={16}
                                color={THEME.colors.textMuted}
                            />
                        </TouchableOpacity>
                    )}
                </View>

                <TouchableOpacity
                    style={styles.addButton}
                    onPress={() =>
                        navigation.navigate("RecetteForm", { recette: null })
                    }
                    activeOpacity={0.8}
                >
                    <Ionicons name="add" size={20} color="#fff" />
                    <Text style={styles.addButtonText}>Nouvelle</Text>
                </TouchableOpacity>
            </View>

            {loading ? (
                <View style={styles.loadingContainer}>
                    <ActivityIndicator
                        size="large"
                        color={THEME.colors.primary}
                    />
                    <Text style={styles.loadingText}>
                        Chargement des recettes...
                    </Text>
                </View>
            ) : (
                <FlatList
                    data={filteredRecettes}
                    keyExtractor={(item) => item.id.toString()}
                    contentContainerStyle={styles.listContent}
                    refreshControl={
                        <RefreshControl
                            refreshing={refreshing}
                            onRefresh={onRefresh}
                            colors={[THEME.colors.primary]}
                        />
                    }
                    ListEmptyComponent={
                        <View style={styles.emptyContainer}>
                            <Ionicons
                                name="book-outline"
                                size={54}
                                color={THEME.colors.textMuted}
                            />
                            <Text style={styles.emptyTitle}>
                                Aucune recette trouvée
                            </Text>
                            <Text style={styles.emptySubtitle}>
                                {search
                                    ? "Essayez une autre recherche."
                                    : 'Commencez par ajouter une nouvelle recette avec le bouton "Nouvelle" !'}
                            </Text>
                        </View>
                    }
                    renderItem={({ item }) => {
                        const ingredientCount =
                            item.recetteIngredients?.length || 0;
                        const realisationCount = item.realisations?.length || 0;

                        return (
                            <TouchableOpacity
                                style={styles.card}
                                activeOpacity={0.7}
                                onPress={() =>
                                    navigation.navigate("RecetteDetail", {
                                        id: item.id,
                                    })
                                }
                            >
                                <View style={styles.cardHeader}>
                                    <View style={styles.cardTitleContainer}>
                                        <Text style={styles.cardEmoji}>🍲</Text>
                                        <Text style={styles.cardTitle}>
                                            {item.designation}
                                        </Text>
                                    </View>
                                    <View style={styles.actionsRow}>
                                        <TouchableOpacity
                                            onPress={() =>
                                                navigation.navigate(
                                                    "RecetteForm",
                                                    { recette: item },
                                                )
                                            }
                                            style={styles.actionIconBtn}
                                        >
                                            <Ionicons
                                                name="pencil-outline"
                                                size={18}
                                                color={THEME.colors.textMuted}
                                            />
                                        </TouchableOpacity>
                                        <TouchableOpacity
                                            onPress={() => handleDelete(item)}
                                            style={[
                                                styles.actionIconBtn,
                                                {
                                                    backgroundColor:
                                                        THEME.colors
                                                            .dangerLight,
                                                },
                                            ]}
                                        >
                                            <Ionicons
                                                name="trash-outline"
                                                size={18}
                                                color={THEME.colors.danger}
                                            />
                                        </TouchableOpacity>
                                    </View>
                                </View>

                                {/* Badges / metadata */}
                                <View style={styles.badgeRow}>
                                    <View style={styles.badge}>
                                        <Ionicons
                                            name="leaf-outline"
                                            size={13}
                                            color={THEME.colors.primary}
                                        />
                                        <Text style={styles.badgeText}>
                                            {ingredientCount} ingrédient
                                            {ingredientCount > 1 ? "s" : ""}
                                        </Text>
                                    </View>
                                    <View
                                        style={[
                                            styles.badge,
                                            styles.badgeSecondary,
                                        ]}
                                    >
                                        <Ionicons
                                            name="time-outline"
                                            size={13}
                                            color={THEME.colors.textMuted}
                                        />
                                        <Text
                                            style={[
                                                styles.badgeText,
                                                {
                                                    color: THEME.colors
                                                        .textMuted,
                                                },
                                            ]}
                                        >
                                            {realisationCount} repas préparé
                                            {realisationCount > 1 ? "s" : ""}
                                        </Text>
                                    </View>
                                </View>

                                {/* Preview of ingredients tags */}
                                {ingredientCount > 0 && (
                                    <View style={styles.tagsContainer}>
                                        {item.recetteIngredients
                                            ?.slice(0, 4)
                                            .map((ri) => (
                                                <View
                                                    key={ri.id}
                                                    style={styles.ingredientTag}
                                                >
                                                    <Text
                                                        style={
                                                            styles.ingredientTagText
                                                        }
                                                    >
                                                        {
                                                            ri.ingredient
                                                                ?.designation
                                                        }
                                                        {ri.quantite
                                                            ? ` (${ri.quantite})`
                                                            : ""}
                                                    </Text>
                                                </View>
                                            ))}
                                        {ingredientCount > 4 && (
                                            <Text style={styles.moreTag}>
                                                +{ingredientCount - 4} autres
                                            </Text>
                                        )}
                                    </View>
                                )}
                            </TouchableOpacity>
                        );
                    }}
                />
            )}
        </View>
    );
};

const styles = StyleSheet.create({
    container: {
        flex: 1,
        backgroundColor: THEME.colors.background,
    },
    topContainer: {
        flexDirection: "row",
        alignItems: "center",
        paddingHorizontal: THEME.spacing.md,
        paddingTop: THEME.spacing.md,
        paddingBottom: THEME.spacing.xs,
        gap: 10,
    },
    searchBar: {
        flex: 1,
        flexDirection: "row",
        alignItems: "center",
        backgroundColor: THEME.colors.card,
        borderRadius: THEME.borderRadius.md,
        paddingHorizontal: 12,
        height: 44,
        borderWidth: 1,
        borderColor: THEME.colors.border,
    },
    searchInput: {
        flex: 1,
        marginLeft: 8,
        fontSize: 14,
        color: THEME.colors.text,
    },
    addButton: {
        backgroundColor: THEME.colors.primary,
        flexDirection: "row",
        alignItems: "center",
        height: 44,
        paddingHorizontal: 14,
        borderRadius: THEME.borderRadius.md,
        shadowColor: THEME.colors.primary,
        shadowOffset: { width: 0, height: 2 },
        shadowOpacity: 0.25,
        shadowRadius: 4,
        elevation: 3,
    },
    addButtonText: {
        color: "#fff",
        fontWeight: "700",
        fontSize: 14,
        marginLeft: 4,
    },
    listContent: {
        padding: THEME.spacing.md,
        paddingBottom: 40,
    },
    card: {
        backgroundColor: THEME.colors.card,
        borderRadius: THEME.borderRadius.lg,
        padding: THEME.spacing.md,
        marginBottom: THEME.spacing.md,
        ...THEME.shadow,
    },
    cardHeader: {
        flexDirection: "row",
        alignItems: "center",
        justifyContent: "space-between",
        marginBottom: 8,
    },
    cardTitleContainer: {
        flexDirection: "row",
        alignItems: "center",
        flex: 1,
        paddingRight: 8,
    },
    cardEmoji: {
        fontSize: 22,
        marginRight: 8,
    },
    cardTitle: {
        fontSize: 16,
        fontWeight: "700",
        color: THEME.colors.text,
        flex: 1,
    },
    actionsRow: {
        flexDirection: "row",
        gap: 8,
    },
    actionIconBtn: {
        padding: 8,
        borderRadius: THEME.borderRadius.sm,
        backgroundColor: THEME.colors.background,
    },
    badgeRow: {
        flexDirection: "row",
        gap: 8,
        marginVertical: 6,
    },
    badge: {
        flexDirection: "row",
        alignItems: "center",
        backgroundColor: THEME.colors.primaryLight,
        paddingVertical: 4,
        paddingHorizontal: 8,
        borderRadius: THEME.borderRadius.sm,
    },
    badgeSecondary: {
        backgroundColor: "#f1f5f9",
    },
    badgeText: {
        fontSize: 12,
        fontWeight: "600",
        color: THEME.colors.primary,
        marginLeft: 4,
    },
    tagsContainer: {
        flexDirection: "row",
        flexWrap: "wrap",
        gap: 6,
        marginTop: 8,
        alignItems: "center",
    },
    ingredientTag: {
        backgroundColor: "#f8fafc",
        borderWidth: 1,
        borderColor: THEME.colors.border,
        paddingVertical: 3,
        paddingHorizontal: 8,
        borderRadius: THEME.borderRadius.sm,
    },
    ingredientTagText: {
        fontSize: 11,
        color: THEME.colors.text,
    },
    moreTag: {
        fontSize: 11,
        color: THEME.colors.textMuted,
        fontStyle: "italic",
    },
    loadingContainer: {
        flex: 1,
        alignItems: "center",
        justifyContent: "center",
        padding: 20,
    },
    loadingText: {
        marginTop: 12,
        fontSize: 14,
        color: THEME.colors.textMuted,
    },
    emptyContainer: {
        alignItems: "center",
        justifyContent: "center",
        paddingVertical: 60,
        paddingHorizontal: 20,
    },
    emptyTitle: {
        fontSize: 17,
        fontWeight: "700",
        color: THEME.colors.text,
        marginTop: 12,
    },
    emptySubtitle: {
        fontSize: 13,
        color: THEME.colors.textMuted,
        textAlign: "center",
        marginTop: 6,
        lineHeight: 18,
    },
});
