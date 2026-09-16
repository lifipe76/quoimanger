import { Ionicons } from "@expo/vector-icons";
import React, { useCallback, useEffect, useState } from "react";
import {
    ActivityIndicator,
    Alert,
    FlatList,
    Modal,
    RefreshControl,
    StyleSheet,
    Text,
    TextInput,
    TouchableOpacity,
    View,
} from "react-native";
import { THEME } from "../../constants/theme";
import { apiRequest } from "../../services/api";
import { Ingredient } from "../../types";

export const IngredientsScreen: React.FC = () => {
    const [ingredients, setIngredients] = useState<Ingredient[]>([]);
    const [loading, setLoading] = useState(true);
    const [refreshing, setRefreshing] = useState(false);
    const [search, setSearch] = useState("");

    // Modal create/edit
    const [modalVisible, setModalVisible] = useState(false);
    const [editingIngredient, setEditingIngredient] =
        useState<Ingredient | null>(null);
    const [designation, setDesignation] = useState("");
    const [saving, setSaving] = useState(false);

    const fetchIngredients = async () => {
        try {
            const data = await apiRequest<any>("/api/ingredients");
            const list: Ingredient[] = Array.isArray(data)
                ? data
                : data?.["hydra:member"] || data?.member || [];
            // Sort alphabetically
            list.sort((a, b) => a.designation.localeCompare(b.designation));
            setIngredients(list);
        } catch (err: any) {
            Alert.alert(
                "Erreur",
                err.message || "Impossible de charger les ingrédients.",
            );
        } finally {
            setLoading(false);
            setRefreshing(false);
        }
    };

    useEffect(() => {
        fetchIngredients();
    }, []);

    const onRefresh = useCallback(() => {
        setRefreshing(true);
        fetchIngredients();
    }, []);

    const openCreateModal = () => {
        setEditingIngredient(null);
        setDesignation("");
        setModalVisible(true);
    };

    const openEditModal = (item: Ingredient) => {
        setEditingIngredient(item);
        setDesignation(item.designation);
        setModalVisible(true);
    };

    const handleSave = async () => {
        if (!designation.trim()) {
            Alert.alert("Erreur", "Veuillez saisir le nom de l'ingrédient.");
            return;
        }

        try {
            setSaving(true);
            if (editingIngredient) {
                await apiRequest(`/api/ingredients/${editingIngredient.id}`, {
                    method: "PUT",
                    body: JSON.stringify({ designation: designation.trim() }),
                });
            } else {
                await apiRequest("/api/ingredients", {
                    method: "POST",
                    body: JSON.stringify({ designation: designation.trim() }),
                });
            }

            setModalVisible(false);
            fetchIngredients();
        } catch (err: any) {
            Alert.alert(
                "Erreur",
                err.message || "Impossible d’enregistrer l’ingrédient.",
            );
        } finally {
            setSaving(false);
        }
    };

    const handleDelete = (item: Ingredient) => {
        Alert.alert(
            "Supprimer l'ingrédient",
            `Voulez-vous supprimer "${item.designation}" ?`,
            [
                { text: "Annuler", style: "cancel" },
                {
                    text: "Supprimer",
                    style: "destructive",
                    onPress: async () => {
                        try {
                            await apiRequest(`/api/ingredients/${item.id}`, {
                                method: "DELETE",
                            });
                            setIngredients((prev) =>
                                prev.filter((i) => i.id !== item.id),
                            );
                        } catch (err: any) {
                            Alert.alert(
                                "Erreur",
                                err.message ||
                                    "Impossible de supprimer l’ingrédient.",
                            );
                        }
                    },
                },
            ],
        );
    };

    const filtered = ingredients.filter((i) =>
        i.designation.toLowerCase().includes(search.toLowerCase()),
    );

    return (
        <View style={styles.container}>
            {/* Top Header */}
            <View style={styles.topContainer}>
                <View style={styles.searchBar}>
                    <Ionicons
                        name="search"
                        size={18}
                        color={THEME.colors.textMuted}
                    />
                    <TextInput
                        style={styles.searchInput}
                        placeholder="Rechercher un ingrédient..."
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
                    onPress={openCreateModal}
                    activeOpacity={0.8}
                >
                    <Ionicons name="add" size={20} color="#fff" />
                    <Text style={styles.addButtonText}>Ajouter</Text>
                </TouchableOpacity>
            </View>

            {/* List */}
            {loading ? (
                <View style={styles.loadingContainer}>
                    <ActivityIndicator
                        size="large"
                        color={THEME.colors.primary}
                    />
                    <Text style={styles.loadingText}>
                        Chargement du garde-manger...
                    </Text>
                </View>
            ) : (
                <FlatList
                    data={filtered}
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
                                name="basket-outline"
                                size={54}
                                color={THEME.colors.textMuted}
                            />
                            <Text style={styles.emptyTitle}>
                                Aucun ingrédient trouvé
                            </Text>
                            <Text style={styles.emptySubtitle}>
                                Ajoutez de nouveaux ingrédients pour composer
                                vos recettes !
                            </Text>
                        </View>
                    }
                    renderItem={({ item }) => (
                        <View style={styles.card}>
                            <View style={styles.cardLeft}>
                                <View style={styles.iconCircle}>
                                    <Ionicons
                                        name="leaf-outline"
                                        size={18}
                                        color={THEME.colors.primary}
                                    />
                                </View>
                                <Text style={styles.ingredientName}>
                                    {item.designation}
                                </Text>
                            </View>

                            <View style={styles.actions}>
                                <TouchableOpacity
                                    onPress={() => openEditModal(item)}
                                    style={styles.actionBtn}
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
                                        styles.actionBtn,
                                        {
                                            backgroundColor:
                                                THEME.colors.dangerLight,
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
                    )}
                />
            )}

            {/* Create / Edit Modal */}
            <Modal
                visible={modalVisible}
                transparent
                animationType="fade"
                onRequestClose={() => setModalVisible(false)}
            >
                <View style={styles.modalOverlay}>
                    <View style={styles.modalCard}>
                        <Text style={styles.modalTitle}>
                            {editingIngredient
                                ? "Modifier l'ingrédient"
                                : "Nouvel ingrédient"}
                        </Text>

                        <TextInput
                            style={styles.modalInput}
                            placeholder="Nom (ex: Carottes, Crème fraîche...)"
                            placeholderTextColor={THEME.colors.textMuted}
                            value={designation}
                            onChangeText={setDesignation}
                            autoFocus
                        />

                        <View style={styles.modalActions}>
                            <TouchableOpacity
                                style={[styles.modalBtn, styles.modalBtnCancel]}
                                onPress={() => setModalVisible(false)}
                            >
                                <Text style={styles.modalBtnCancelText}>
                                    Annuler
                                </Text>
                            </TouchableOpacity>
                            <TouchableOpacity
                                style={[
                                    styles.modalBtn,
                                    styles.modalBtnSave,
                                    saving && { opacity: 0.6 },
                                ]}
                                onPress={handleSave}
                                disabled={saving}
                            >
                                {saving ? (
                                    <ActivityIndicator
                                        color="#fff"
                                        size="small"
                                    />
                                ) : (
                                    <Text style={styles.modalBtnSaveText}>
                                        Enregistrer
                                    </Text>
                                )}
                            </TouchableOpacity>
                        </View>
                    </View>
                </View>
            </Modal>
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
        flexDirection: "row",
        alignItems: "center",
        justifyContent: "space-between",
        backgroundColor: THEME.colors.card,
        borderRadius: THEME.borderRadius.md,
        padding: THEME.spacing.md,
        marginBottom: 8,
        ...THEME.shadow,
    },
    cardLeft: {
        flexDirection: "row",
        alignItems: "center",
        flex: 1,
    },
    iconCircle: {
        width: 36,
        height: 36,
        borderRadius: 18,
        backgroundColor: THEME.colors.primaryLight,
        alignItems: "center",
        justifyContent: "center",
        marginRight: 12,
    },
    ingredientName: {
        fontSize: 15,
        fontWeight: "600",
        color: THEME.colors.text,
        flex: 1,
    },
    actions: {
        flexDirection: "row",
        gap: 8,
    },
    actionBtn: {
        padding: 8,
        borderRadius: THEME.borderRadius.sm,
        backgroundColor: THEME.colors.background,
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
    modalOverlay: {
        flex: 1,
        backgroundColor: "rgba(15, 23, 42, 0.45)",
        alignItems: "center",
        justifyContent: "center",
        padding: THEME.spacing.lg,
    },
    modalCard: {
        width: "100%",
        backgroundColor: THEME.colors.card,
        borderRadius: THEME.borderRadius.lg,
        padding: THEME.spacing.lg,
        ...THEME.shadow,
    },
    modalTitle: {
        fontSize: 18,
        fontWeight: "700",
        color: THEME.colors.text,
        marginBottom: THEME.spacing.md,
    },
    modalInput: {
        borderWidth: 1,
        borderColor: THEME.colors.border,
        borderRadius: THEME.borderRadius.md,
        paddingHorizontal: 12,
        height: 48,
        fontSize: 15,
        marginBottom: THEME.spacing.lg,
        color: THEME.colors.text,
    },
    modalActions: {
        flexDirection: "row",
        justifyContent: "flex-end",
        gap: 12,
    },
    modalBtn: {
        paddingVertical: 10,
        paddingHorizontal: 16,
        borderRadius: THEME.borderRadius.md,
    },
    modalBtnCancel: {
        backgroundColor: THEME.colors.border,
    },
    modalBtnCancelText: {
        color: THEME.colors.text,
        fontWeight: "600",
        fontSize: 14,
    },
    modalBtnSave: {
        backgroundColor: THEME.colors.primary,
    },
    modalBtnSaveText: {
        color: "#fff",
        fontWeight: "700",
        fontSize: 14,
    },
});
