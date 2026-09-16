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
import { Recette, RecetteRealisation } from "../../types";

interface MealGroup {
    dateKey: string;
    formattedDate: string;
    meals: RecetteRealisation[];
    daysDiffWithNext?: number; // Days difference with the chronologically preceding day in list
}

export const TimelineScreen: React.FC = () => {
    const [realisations, setRealisations] = useState<RecetteRealisation[]>([]);
    const [recettes, setRecettes] = useState<Recette[]>([]);
    const [loading, setLoading] = useState(true);
    const [refreshing, setRefreshing] = useState(false);

    // Edit mode toggle (shows/hides delete trash cans)
    const [isEditMode, setIsEditMode] = useState(false);

    // Modal Add Meal
    const [modalVisible, setModalVisible] = useState(false);
    const [searchRecette, setSearchRecette] = useState("");
    const [submitting, setSubmitting] = useState(false);

    const fetchData = async () => {
        try {
            const [realsData, recettesData] = await Promise.all([
                apiRequest<any>("/api/recette_realisations"),
                apiRequest<any>("/api/recettes"),
            ]);

            const recsList: Recette[] = Array.isArray(recettesData)
                ? recettesData
                : recettesData?.["hydra:member"] || recettesData?.member || [];
            setRecettes(recsList);

            const recMap = new Map<number, Recette>();
            recsList.forEach((r) => recMap.set(r.id, r));

            const rawReals: any[] = Array.isArray(realsData)
                ? realsData
                : realsData?.["hydra:member"] || realsData?.member || [];

            // Map meals and resolve recipe designation reliably
            const realsList: RecetteRealisation[] = rawReals.map((item) => {
                let recetteObj = item.recette;

                if (typeof item.recette === "string") {
                    const match = item.recette.match(/\/(\d+)$/);
                    const recId = match ? parseInt(match[1], 10) : null;
                    if (recId && recMap.has(recId)) {
                        recetteObj = recMap.get(recId);
                    }
                } else if (item.recette && typeof item.recette === "object") {
                    if (
                        !item.recette.designation &&
                        item.recette.id &&
                        recMap.has(item.recette.id)
                    ) {
                        recetteObj = recMap.get(item.recette.id);
                    }
                }

                return {
                    ...item,
                    recette: recetteObj,
                };
            });

            // Sort by realiseAt DESC (newest first)
            realsList.sort(
                (a, b) =>
                    new Date(b.realiseAt).getTime() -
                    new Date(a.realiseAt).getTime(),
            );
            setRealisations(realsList);
        } catch (err: any) {
            Alert.alert(
                "Erreur",
                err.message || "Impossible de charger les données.",
            );
        } finally {
            setLoading(false);
            setRefreshing(false);
        }
    };

    useEffect(() => {
        fetchData();
    }, []);

    const onRefresh = useCallback(() => {
        setRefreshing(true);
        fetchData();
    }, []);

    const handleAddRealisation = async (recette: Recette) => {
        try {
            setSubmitting(true);
            await apiRequest("/api/recette_realisations", {
                method: "POST",
                body: JSON.stringify({
                    recette: `/api/recettes/${recette.id}`,
                    realiseAt: new Date().toISOString(),
                }),
            });
            setModalVisible(false);
            setSearchRecette("");
            fetchData();
        } catch (err: any) {
            Alert.alert(
                "Erreur",
                err.message || "Impossible d’enregistrer le repas.",
            );
        } finally {
            setSubmitting(false);
        }
    };

    const handleDeleteRealisation = (item: RecetteRealisation) => {
        Alert.alert(
            "Supprimer le repas",
            `Voulez-vous supprimer "${item.recette?.designation || "ce repas"}" de votre historique ?`,
            [
                { text: "Annuler", style: "cancel" },
                {
                    text: "Supprimer",
                    style: "destructive",
                    onPress: async () => {
                        try {
                            await apiRequest(
                                `/api/recette_realisations/${item.id}`,
                                {
                                    method: "DELETE",
                                },
                            );
                            setRealisations((prev) =>
                                prev.filter((r) => r.id !== item.id),
                            );
                        } catch (err: any) {
                            Alert.alert(
                                "Erreur",
                                err.message ||
                                    "Impossible de supprimer le repas.",
                            );
                        }
                    },
                },
            ],
        );
    };

    const formatDateHeader = (dateStr: string) => {
        const date = new Date(dateStr);
        const today = new Date();
        const yesterday = new Date();
        yesterday.setDate(today.getDate() - 1);

        const isSameDay = (d1: Date, d2: Date) =>
            d1.getFullYear() === d2.getFullYear() &&
            d1.getMonth() === d2.getMonth() &&
            d1.getDate() === d2.getDate();

        if (isSameDay(date, today)) {
            return "Aujourd'hui";
        }
        if (isSameDay(date, yesterday)) {
            return "Hier";
        }

        return date.toLocaleDateString("fr-FR", {
            weekday: "short",
            day: "numeric",
            month: "long",
            year: "numeric",
        });
    };

    // Group realisations by day
    const groupedMeals: MealGroup[] = realisations.reduce(
        (groups: MealGroup[], meal) => {
            const date = new Date(meal.realiseAt);
            const dateKey = `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, "0")}-${String(date.getDate()).padStart(2, "0")}`;

            let group = groups.find((g) => g.dateKey === dateKey);
            if (!group) {
                group = {
                    dateKey,
                    formattedDate: formatDateHeader(meal.realiseAt),
                    meals: [],
                };
                groups.push(group);
            }
            group.meals.push(meal);
            return groups;
        },
        [],
    );

    // Compute consecutive day gaps
    for (let i = 0; i < groupedMeals.length - 1; i++) {
        const currentDate = new Date(groupedMeals[i].dateKey + "T00:00:00");
        const nextDate = new Date(groupedMeals[i + 1].dateKey + "T00:00:00");
        const diffDays = Math.round(
            (currentDate.getTime() - nextDate.getTime()) /
                (1000 * 60 * 60 * 24),
        );
        groupedMeals[i].daysDiffWithNext = diffDays;
    }

    const filteredRecettes = recettes.filter((r) =>
        r.designation.toLowerCase().includes(searchRecette.toLowerCase()),
    );

    return (
        <View style={styles.container}>
            {/* Top Action Header: Centered narrowed "Votre repas" button + Discreet edit pencil button */}
            <View style={styles.topActionContainer}>
                {/* Left empty balance spacer to ensure perfect centering of the button */}
                <View style={styles.topHeaderSideSpacer} />

                {/* Centered button */}
                <TouchableOpacity
                    style={styles.addMealButton}
                    onPress={() => setModalVisible(true)}
                    activeOpacity={0.85}
                >
                    <Ionicons name="add" size={17} color="#fff" />
                    <Text style={styles.addMealButtonText}>Votre repas</Text>
                </TouchableOpacity>

                {/* Discreet edit mode toggle button positioned on the right */}
                <TouchableOpacity
                    style={[
                        styles.editToggleButton,
                        isEditMode && styles.editToggleButtonActive,
                    ]}
                    onPress={() => setIsEditMode((prev) => !prev)}
                    activeOpacity={0.7}
                    hitSlop={{ top: 8, bottom: 8, left: 8, right: 8 }}
                >
                    <Ionicons
                        name={isEditMode ? "checkmark" : "pencil-outline"}
                        size={17}
                        color={isEditMode ? "#ffffff" : THEME.colors.textMuted}
                    />
                </TouchableOpacity>
            </View>

            {/* Content / Timeline */}
            {loading ? (
                <View style={styles.loadingContainer}>
                    <ActivityIndicator
                        size="large"
                        color={THEME.colors.primary}
                    />
                    <Text style={styles.loadingText}>
                        Chargement des repas...
                    </Text>
                </View>
            ) : (
                <FlatList
                    data={groupedMeals}
                    keyExtractor={(item) => item.dateKey}
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
                                name="restaurant-outline"
                                size={52}
                                color={THEME.colors.textMuted}
                            />
                            <Text style={styles.emptyTitle}>
                                Aucun repas enregistré
                            </Text>
                            <Text style={styles.emptySubtitle}>
                                Cliquez sur "Votre repas" pour renseigner votre
                                premier repas !
                            </Text>
                        </View>
                    }
                    renderItem={({ item: group, index: groupIndex }) => {
                        const isLastGroup =
                            groupIndex === groupedMeals.length - 1;
                        const isConsecutive = group.daysDiffWithNext === 1;

                        return (
                            <View style={styles.groupContainer}>
                                {/* Date Header Pill */}
                                <View style={styles.dateHeaderRow}>
                                    <View style={styles.dateBadge}>
                                        <Text style={styles.dateBadgeText}>
                                            {group.formattedDate}
                                        </Text>
                                    </View>
                                </View>

                                {/* Meals List under this date */}
                                <View style={styles.mealsWrapper}>
                                    {group.meals.map((meal, mealIndex) => {
                                        const isLastMealInGroup =
                                            mealIndex ===
                                            group.meals.length - 1;
                                        const showBottomContinuous =
                                            !isLastMealInGroup ||
                                            (!isLastGroup && isConsecutive);

                                        return (
                                            <View
                                                key={meal.id}
                                                style={styles.mealContainer}
                                            >
                                                {/* Centered vertical line that runs behind the item */}
                                                <View
                                                    style={
                                                        styles.verticalTrackColumn
                                                    }
                                                >
                                                    {showBottomContinuous ? (
                                                        <View
                                                            style={
                                                                styles.trackLineBottom
                                                            }
                                                        />
                                                    ) : (
                                                        <View
                                                            style={
                                                                styles.trackLineEmpty
                                                            }
                                                        />
                                                    )}
                                                </View>

                                                {/* Meal Card with centered name */}
                                                <View style={styles.mealCard}>
                                                    {/* Left spacer for perfect centering when edit trash is visible */}
                                                    {isEditMode && (
                                                        <View
                                                            style={
                                                                styles.spacer
                                                            }
                                                        />
                                                    )}

                                                    <View
                                                        style={
                                                            styles.recetteNameWrapper
                                                        }
                                                    >
                                                        <Text
                                                            style={
                                                                styles.recetteName
                                                            }
                                                            numberOfLines={2}
                                                        >
                                                            {meal.recette
                                                                ?.designation ||
                                                                ""}
                                                        </Text>
                                                    </View>

                                                    {/* Trash button visible only in edit mode */}
                                                    {isEditMode && (
                                                        <TouchableOpacity
                                                            onPress={() =>
                                                                handleDeleteRealisation(
                                                                    meal,
                                                                )
                                                            }
                                                            style={
                                                                styles.deleteButton
                                                            }
                                                            hitSlop={{
                                                                top: 10,
                                                                bottom: 10,
                                                                left: 10,
                                                                right: 10,
                                                            }}
                                                        >
                                                            <Ionicons
                                                                name="trash-outline"
                                                                size={18}
                                                                color={
                                                                    THEME.colors
                                                                        .danger
                                                                }
                                                            />
                                                        </TouchableOpacity>
                                                    )}
                                                </View>
                                            </View>
                                        );
                                    })}
                                </View>

                                {/* Inter-day transition: dotted gap if days are not consecutive */}
                                {!isLastGroup &&
                                    (isConsecutive ? (
                                        // Continuous solid line between consecutive days
                                        <View
                                            style={styles.continuousConnector}
                                        >
                                            <View
                                                style={
                                                    styles.continuousSolidLine
                                                }
                                            />
                                        </View>
                                    ) : (
                                        // Non-consecutive: larger space with dots
                                        <View style={styles.dottedGapContainer}>
                                            <View
                                                style={styles.dottedLineSegment}
                                            />
                                            <View
                                                style={styles.dottedLineSegment}
                                            />
                                            <View
                                                style={styles.dottedLineSegment}
                                            />
                                            <View
                                                style={styles.dottedLineSegment}
                                            />
                                            <View
                                                style={styles.dottedLineSegment}
                                            />
                                        </View>
                                    ))}
                            </View>
                        );
                    }}
                />
            )}

            {/* Add Meal Modal */}
            <Modal
                visible={modalVisible}
                animationType="slide"
                presentationStyle="pageSheet"
                onRequestClose={() => setModalVisible(false)}
            >
                <View style={styles.modalContainer}>
                    <View style={styles.modalHeader}>
                        <Text style={styles.modalTitle}>
                            Quel repas avez-vous cuisiné ?
                        </Text>
                        <TouchableOpacity
                            onPress={() => setModalVisible(false)}
                            style={styles.modalCloseBtn}
                        >
                            <Ionicons
                                name="close"
                                size={24}
                                color={THEME.colors.text}
                            />
                        </TouchableOpacity>
                    </View>

                    {/* Search bar inside modal */}
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
                            value={searchRecette}
                            onChangeText={setSearchRecette}
                        />
                        {searchRecette.length > 0 && (
                            <TouchableOpacity
                                onPress={() => setSearchRecette("")}
                            >
                                <Ionicons
                                    name="close-circle"
                                    size={16}
                                    color={THEME.colors.textMuted}
                                />
                            </TouchableOpacity>
                        )}
                    </View>

                    {submitting ? (
                        <View style={styles.loadingContainer}>
                            <ActivityIndicator
                                size="large"
                                color={THEME.colors.primary}
                            />
                            <Text style={styles.loadingText}>
                                Enregistrement de votre repas...
                            </Text>
                        </View>
                    ) : (
                        <FlatList
                            data={filteredRecettes}
                            keyExtractor={(item) => item.id.toString()}
                            contentContainerStyle={{ paddingBottom: 40 }}
                            ListEmptyComponent={
                                <View style={styles.emptyContainer}>
                                    <Text style={styles.emptyTitle}>
                                        Aucune recette trouvée
                                    </Text>
                                </View>
                            }
                            renderItem={({ item }) => (
                                <TouchableOpacity
                                    style={styles.recetteOption}
                                    onPress={() => handleAddRealisation(item)}
                                    activeOpacity={0.7}
                                >
                                    <View style={styles.recetteOptionIcon}>
                                        <Ionicons
                                            name="restaurant-outline"
                                            size={20}
                                            color={THEME.colors.primary}
                                        />
                                    </View>
                                    <View style={{ flex: 1, marginLeft: 12 }}>
                                        <Text style={styles.recetteOptionTitle}>
                                            {item.designation}
                                        </Text>
                                        {item.recetteIngredients &&
                                            item.recetteIngredients.length >
                                                0 && (
                                                <Text
                                                    style={
                                                        styles.recetteOptionSub
                                                    }
                                                    numberOfLines={1}
                                                >
                                                    {item.recetteIngredients
                                                        .map(
                                                            (ri) =>
                                                                ri.ingredient
                                                                    ?.designation,
                                                        )
                                                        .filter(Boolean)
                                                        .join(", ")}
                                                </Text>
                                            )}
                                    </View>
                                    <Ionicons
                                        name="chevron-forward"
                                        size={18}
                                        color={THEME.colors.textMuted}
                                    />
                                </TouchableOpacity>
                            )}
                        />
                    )}
                </View>
            </Modal>
        </View>
    );
};

const styles = StyleSheet.create({
    container: {
        flex: 1,
        backgroundColor: "#ffffff",
    },
    topActionContainer: {
        paddingHorizontal: 20,
        paddingTop: 16,
        paddingBottom: 12,
        backgroundColor: "#ffffff",
        flexDirection: "row",
        alignItems: "center",
        justifyContent: "space-between",
    },
    topHeaderSideSpacer: {
        width: 38,
    },
    addMealButton: {
        backgroundColor: THEME.colors.primary,
        flexDirection: "row",
        alignItems: "center",
        justifyContent: "center",
        paddingVertical: 9,
        paddingHorizontal: 16,
        borderRadius: 20,
    },
    addMealButtonText: {
        color: "#ffffff",
        fontWeight: "700",
        fontSize: 14,
        marginLeft: 6,
    },
    editToggleButton: {
        width: 38,
        height: 38,
        borderRadius: 10,
        backgroundColor: "#f8fafc",
        alignItems: "center",
        justifyContent: "center",
        borderWidth: 1,
        borderColor: "#f1f5f9",
    },
    editToggleButtonActive: {
        backgroundColor: THEME.colors.primary,
        borderColor: THEME.colors.primary,
    },
    listContent: {
        paddingHorizontal: 20,
        paddingTop: 8,
        paddingBottom: 50,
    },
    groupContainer: {
        alignItems: "center",
    },
    dateHeaderRow: {
        alignItems: "center",
        justifyContent: "center",
        marginVertical: 14,
        zIndex: 2,
    },
    dateBadge: {
        backgroundColor: "#f8fafc",
        paddingVertical: 6,
        paddingHorizontal: 16,
        borderRadius: 20,
        borderWidth: 1,
        borderColor: "#f1f5f9",
    },
    dateBadgeText: {
        fontSize: 13,
        fontWeight: "700",
        color: THEME.colors.primary,
        textTransform: "capitalize",
        textAlign: "center",
    },
    mealsWrapper: {
        width: "100%",
    },
    mealContainer: {
        position: "relative",
        alignItems: "center",
        justifyContent: "center",
        minHeight: 58,
        marginVertical: 2,
    },
    verticalTrackColumn: {
        position: "absolute",
        top: 0,
        bottom: 0,
        left: 0,
        right: 0,
        alignItems: "center",
        justifyContent: "center",
        zIndex: 1,
    },
    trackLineTop: {
        flex: 1,
        width: 1,
        backgroundColor: "#cbd5e1",
    },
    timelineDot: {
        width: 10,
        height: 10,
        borderRadius: 5,
        backgroundColor: THEME.colors.primary,
        borderWidth: 1,
        borderColor: "#ffffff",
    },
    trackLineBottom: {
        flex: 1,
        width: 1,
        backgroundColor: "#cbd5e1",
    },
    trackLineEmpty: {
        flex: 1,
        width: 1,
        backgroundColor: "transparent",
    },
    mealCard: {
        width: "100%",
        flexDirection: "row",
        alignItems: "center",
        justifyContent: "center",
        paddingVertical: 10,
        paddingHorizontal: 8,
        zIndex: 2,
    },
    spacer: {
        width: 36,
    },
    recetteNameWrapper: {
        flex: 1,
        alignItems: "center",
        justifyContent: "center",
        backgroundColor: "#ffffff",
        paddingHorizontal: 16,
        paddingVertical: 6,
        borderRadius: 8,
    },
    recetteName: {
        fontSize: 16,
        fontWeight: "700",
        color: THEME.colors.text,
        textAlign: "center",
    },
    deleteButton: {
        width: 36,
        height: 36,
        alignItems: "center",
        justifyContent: "center",
    },
    continuousConnector: {
        alignItems: "center",
        justifyContent: "center",
        height: 24,
        width: "100%",
    },
    continuousSolidLine: {
        width: 1,
        height: "100%",
        backgroundColor: "#cbd5e1",
    },
    dottedGapContainer: {
        alignItems: "center",
        justifyContent: "space-evenly",
        height: 48,
        width: "100%",
        marginVertical: 4,
    },
    dottedLineSegment: {
        width: 3,
        height: 4,
        borderRadius: 1.5,
        backgroundColor: "#94a3b8",
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
    modalContainer: {
        flex: 1,
        backgroundColor: "#ffffff",
        padding: 20,
    },
    modalHeader: {
        flexDirection: "row",
        alignItems: "center",
        justifyContent: "space-between",
        paddingVertical: 12,
    },
    modalTitle: {
        fontSize: 18,
        fontWeight: "700",
        color: THEME.colors.text,
    },
    modalCloseBtn: {
        padding: 4,
    },
    searchBar: {
        flexDirection: "row",
        alignItems: "center",
        backgroundColor: "#f8fafc",
        borderRadius: 10,
        paddingHorizontal: 12,
        height: 44,
        marginBottom: 16,
    },
    searchInput: {
        flex: 1,
        marginLeft: 8,
        fontSize: 14,
        color: THEME.colors.text,
    },
    recetteOption: {
        flexDirection: "row",
        alignItems: "center",
        backgroundColor: "#ffffff",
        paddingVertical: 12,
        paddingHorizontal: 12,
        borderRadius: 10,
        marginBottom: 8,
    },
    recetteOptionIcon: {
        width: 38,
        height: 38,
        borderRadius: 19,
        backgroundColor: "#fff7f2",
        alignItems: "center",
        justifyContent: "center",
    },
    recetteOptionTitle: {
        fontSize: 15,
        fontWeight: "600",
        color: THEME.colors.text,
    },
    recetteOptionSub: {
        fontSize: 12,
        color: THEME.colors.textMuted,
        marginTop: 2,
    },
});
