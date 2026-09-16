import { Ionicons } from "@expo/vector-icons";
import React, { useEffect, useState } from "react";
import {
    ActivityIndicator,
    Alert,
    FlatList,
    Modal,
    ScrollView,
    StyleSheet,
    Text,
    TextInput,
    TouchableOpacity,
    View,
} from "react-native";
import { THEME } from "../../constants/theme";
import { apiRequest } from "../../services/api";
import { Ingredient, Recette } from "../../types";

interface RecetteFormScreenProps {
    route: any;
    navigation: any;
}

interface IngredientRow {
    ingredientId: number;
    designation: string;
    quantite: string;
}

export const RecetteFormScreen: React.FC<RecetteFormScreenProps> = ({
    route,
    navigation,
}) => {
    const recette: Recette | null = route.params?.recette || null;
    const isEditing = !!recette;

    const [designation, setDesignation] = useState(recette?.designation || "");
    const [selectedIngredients, setSelectedIngredients] = useState<
        IngredientRow[]
    >([]);
    const [allIngredients, setAllIngredients] = useState<Ingredient[]>([]);
    const [loading, setLoading] = useState(false);
    const [modalVisible, setModalVisible] = useState(false);
    const [searchIngredient, setSearchIngredient] = useState("");

    useEffect(() => {
        // Load existing ingredients if editing
        if (recette?.recetteIngredients) {
            setSelectedIngredients(
                recette.recetteIngredients.map((ri) => ({
                    ingredientId: ri.ingredient.id,
                    designation: ri.ingredient.designation,
                    quantite: ri.quantite || "",
                })),
            );
        }

        // Load full list of ingredients for picker
        apiRequest<any>("/api/ingredients").then((data) => {
            const list: Ingredient[] = Array.isArray(data)
                ? data
                : data?.["hydra:member"] || data?.member || [];
            setAllIngredients(list);
        });
    }, [recette]);

    const handleAddIngredient = (ingredient: Ingredient) => {
        if (selectedIngredients.some((i) => i.ingredientId === ingredient.id)) {
            Alert.alert("Info", "Cet ingrédient est déjà dans la recette.");
            return;
        }
        setSelectedIngredients((prev) => [
            ...prev,
            {
                ingredientId: ingredient.id,
                designation: ingredient.designation,
                quantite: "",
            },
        ]);
        setModalVisible(false);
        setSearchIngredient("");
    };

    const handleRemoveIngredient = (ingredientId: number) => {
        setSelectedIngredients((prev) =>
            prev.filter((i) => i.ingredientId !== ingredientId),
        );
    };

    const handleQuantityChange = (ingredientId: number, quantite: string) => {
        setSelectedIngredients((prev) =>
            prev.map((i) =>
                i.ingredientId === ingredientId ? { ...i, quantite } : i,
            ),
        );
    };

    const handleSave = async () => {
        if (!designation.trim()) {
            Alert.alert("Erreur", "Veuillez renseigner le nom de la recette.");
            return;
        }

        try {
            setLoading(true);

            let savedRecette: Recette;

            if (isEditing) {
                // Update designation
                savedRecette = await apiRequest<Recette>(
                    `/api/recettes/${recette.id}`,
                    {
                        method: "PUT",
                        body: JSON.stringify({
                            designation: designation.trim(),
                        }),
                    },
                );

                // Sync ingredients: remove old associations then add new
                if (recette.recetteIngredients) {
                    for (const ri of recette.recetteIngredients) {
                        await apiRequest(`/api/recette_ingredients/${ri.id}`, {
                            method: "DELETE",
                        }).catch(() => {});
                    }
                }
            } else {
                // Create new recipe
                savedRecette = await apiRequest<Recette>("/api/recettes", {
                    method: "POST",
                    body: JSON.stringify({
                        designation: designation.trim(),
                    }),
                });
            }

            // Add selected ingredients
            for (const item of selectedIngredients) {
                await apiRequest("/api/recette_ingredients", {
                    method: "POST",
                    body: JSON.stringify({
                        recette: `/api/recettes/${savedRecette.id}`,
                        ingredient: `/api/ingredients/${item.ingredientId}`,
                        quantite: item.quantite.trim() || null,
                    }),
                });
            }

            navigation.goBack();
        } catch (err: any) {
            Alert.alert(
                "Erreur",
                err.message || "Impossible d’enregistrer la recette.",
            );
        } finally {
            setLoading(false);
        }
    };

    const filteredIngredients = allIngredients.filter((ing) =>
        ing.designation.toLowerCase().includes(searchIngredient.toLowerCase()),
    );

    return (
        <View style={styles.container}>
            <ScrollView
                contentContainerStyle={styles.scrollContent}
                keyboardShouldPersistTaps="handled"
            >
                <View style={styles.card}>
                    <Text style={styles.label}>Nom de la recette</Text>
                    <TextInput
                        style={styles.input}
                        placeholder="Ex: Tarte aux poireaux, Poulet rôti..."
                        placeholderTextColor={THEME.colors.textMuted}
                        value={designation}
                        onChangeText={setDesignation}
                    />
                </View>

                {/* Ingredients section */}
                <View style={styles.card}>
                    <View style={styles.sectionHeader}>
                        <View>
                            <Text style={styles.sectionTitle}>
                                Ingrédients de la recette
                            </Text>
                            <Text style={styles.sectionSubtitle}>
                                Ajoutez les ingrédients et leurs quantités
                            </Text>
                        </View>
                        <TouchableOpacity
                            style={styles.addIngBtn}
                            onPress={() => setModalVisible(true)}
                            activeOpacity={0.8}
                        >
                            <Ionicons name="add" size={18} color="#fff" />
                            <Text style={styles.addIngBtnText}>Ajouter</Text>
                        </TouchableOpacity>
                    </View>

                    {selectedIngredients.length === 0 ? (
                        <Text style={styles.emptyIngredientsText}>
                            Aucun ingrédient ajouté pour le moment.
                        </Text>
                    ) : (
                        selectedIngredients.map((item) => (
                            <View
                                key={item.ingredientId}
                                style={styles.ingredientRow}
                            >
                                <View style={{ flex: 1 }}>
                                    <Text style={styles.ingredientRowName}>
                                        {item.designation}
                                    </Text>
                                    <TextInput
                                        style={styles.quantiteInput}
                                        placeholder="Quantité (ex: 200g, 2 cuillères...)"
                                        placeholderTextColor={
                                            THEME.colors.textMuted
                                        }
                                        value={item.quantite}
                                        onChangeText={(val) =>
                                            handleQuantityChange(
                                                item.ingredientId,
                                                val,
                                            )
                                        }
                                    />
                                </View>
                                <TouchableOpacity
                                    onPress={() =>
                                        handleRemoveIngredient(
                                            item.ingredientId,
                                        )
                                    }
                                    style={styles.removeIngBtn}
                                >
                                    <Ionicons
                                        name="trash-outline"
                                        size={20}
                                        color={THEME.colors.danger}
                                    />
                                </TouchableOpacity>
                            </View>
                        ))
                    )}
                </View>

                {/* Submit */}
                <TouchableOpacity
                    style={[styles.saveBtn, loading && styles.saveBtnDisabled]}
                    onPress={handleSave}
                    disabled={loading}
                    activeOpacity={0.8}
                >
                    {loading ? (
                        <ActivityIndicator color="#fff" />
                    ) : (
                        <Text style={styles.saveBtnText}>
                            {isEditing
                                ? "Mettre à jour la recette"
                                : "Créer la recette"}
                        </Text>
                    )}
                </TouchableOpacity>
            </ScrollView>

            {/* Ingredient Picker Modal */}
            <Modal
                visible={modalVisible}
                animationType="slide"
                presentationStyle="pageSheet"
                onRequestClose={() => setModalVisible(false)}
            >
                <View style={styles.modalContainer}>
                    <View style={styles.modalHeader}>
                        <Text style={styles.modalTitle}>
                            Sélectionner un ingrédient
                        </Text>
                        <TouchableOpacity
                            onPress={() => setModalVisible(false)}
                        >
                            <Ionicons
                                name="close"
                                size={24}
                                color={THEME.colors.text}
                            />
                        </TouchableOpacity>
                    </View>

                    <View style={styles.modalSearch}>
                        <Ionicons
                            name="search"
                            size={18}
                            color={THEME.colors.textMuted}
                        />
                        <TextInput
                            style={styles.modalSearchInput}
                            placeholder="Rechercher un ingrédient..."
                            placeholderTextColor={THEME.colors.textMuted}
                            value={searchIngredient}
                            onChangeText={setSearchIngredient}
                        />
                    </View>

                    <FlatList
                        data={filteredIngredients}
                        keyExtractor={(item) => item.id.toString()}
                        renderItem={({ item }) => (
                            <TouchableOpacity
                                style={styles.modalItem}
                                onPress={() => handleAddIngredient(item)}
                            >
                                <Text style={styles.modalItemText}>
                                    {item.designation}
                                </Text>
                                <Ionicons
                                    name="add-circle-outline"
                                    size={22}
                                    color={THEME.colors.primary}
                                />
                            </TouchableOpacity>
                        )}
                    />
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
    scrollContent: {
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
    label: {
        fontSize: 14,
        fontWeight: "700",
        color: THEME.colors.text,
        marginBottom: 8,
    },
    input: {
        borderWidth: 1,
        borderColor: THEME.colors.border,
        borderRadius: THEME.borderRadius.md,
        paddingHorizontal: 12,
        height: 48,
        fontSize: 15,
        backgroundColor: "#fafbfc",
        color: THEME.colors.text,
    },
    sectionHeader: {
        flexDirection: "row",
        alignItems: "center",
        justifyContent: "space-between",
        marginBottom: THEME.spacing.md,
    },
    sectionTitle: {
        fontSize: 15,
        fontWeight: "700",
        color: THEME.colors.text,
    },
    sectionSubtitle: {
        fontSize: 12,
        color: THEME.colors.textMuted,
        marginTop: 2,
    },
    addIngBtn: {
        backgroundColor: THEME.colors.primary,
        flexDirection: "row",
        alignItems: "center",
        paddingVertical: 8,
        paddingHorizontal: 12,
        borderRadius: THEME.borderRadius.md,
    },
    addIngBtnText: {
        color: "#fff",
        fontSize: 13,
        fontWeight: "700",
        marginLeft: 4,
    },
    emptyIngredientsText: {
        fontSize: 13,
        color: THEME.colors.textMuted,
        fontStyle: "italic",
        textAlign: "center",
        paddingVertical: 12,
    },
    ingredientRow: {
        flexDirection: "row",
        alignItems: "center",
        paddingVertical: 10,
        borderBottomWidth: 1,
        borderColor: THEME.colors.border,
    },
    ingredientRowName: {
        fontSize: 15,
        fontWeight: "600",
        color: THEME.colors.text,
    },
    quantiteInput: {
        borderWidth: 1,
        borderColor: THEME.colors.border,
        borderRadius: THEME.borderRadius.sm,
        paddingHorizontal: 10,
        height: 38,
        fontSize: 13,
        marginTop: 6,
        backgroundColor: "#f8fafc",
        color: THEME.colors.text,
    },
    removeIngBtn: {
        padding: 10,
        marginLeft: 10,
    },
    saveBtn: {
        backgroundColor: THEME.colors.primary,
        borderRadius: THEME.borderRadius.md,
        height: 50,
        alignItems: "center",
        justifyContent: "center",
        shadowColor: THEME.colors.primary,
        shadowOffset: { width: 0, height: 4 },
        shadowOpacity: 0.25,
        shadowRadius: 8,
        elevation: 4,
    },
    saveBtnDisabled: {
        opacity: 0.6,
    },
    saveBtnText: {
        color: "#fff",
        fontSize: 16,
        fontWeight: "700",
    },
    modalContainer: {
        flex: 1,
        backgroundColor: THEME.colors.background,
        padding: THEME.spacing.md,
    },
    modalHeader: {
        flexDirection: "row",
        alignItems: "center",
        justifyContent: "space-between",
        paddingVertical: THEME.spacing.md,
    },
    modalTitle: {
        fontSize: 18,
        fontWeight: "700",
        color: THEME.colors.text,
    },
    modalSearch: {
        flexDirection: "row",
        alignItems: "center",
        backgroundColor: THEME.colors.card,
        borderRadius: THEME.borderRadius.md,
        paddingHorizontal: 12,
        height: 44,
        borderWidth: 1,
        borderColor: THEME.colors.border,
        marginBottom: THEME.spacing.md,
    },
    modalSearchInput: {
        flex: 1,
        marginLeft: 8,
        fontSize: 14,
        color: THEME.colors.text,
    },
    modalItem: {
        flexDirection: "row",
        alignItems: "center",
        justifyContent: "space-between",
        paddingVertical: 14,
        paddingHorizontal: 12,
        backgroundColor: THEME.colors.card,
        borderRadius: THEME.borderRadius.md,
        marginBottom: 8,
    },
    modalItemText: {
        fontSize: 15,
        fontWeight: "500",
        color: THEME.colors.text,
    },
});
