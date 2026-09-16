import { Ionicons } from "@expo/vector-icons";
import React, { useState } from "react";
import {
    ActivityIndicator,
    Alert,
    ScrollView,
    StyleSheet,
    Text,
    TextInput,
    TouchableOpacity,
    View,
} from "react-native";
import { THEME } from "../../constants/theme";
import { useAuth } from "../../context/AuthContext";

export const ProfileScreen: React.FC = () => {
    const { user, logout, updateUser } = useAuth();

    const [prenom, setPrenom] = useState(user?.prenom || "");
    const [nom, setNom] = useState(user?.nom || "");
    const [password, setPassword] = useState("");
    const [saving, setSaving] = useState(false);

    const handleUpdate = async () => {
        try {
            setSaving(true);
            const payload: any = {
                prenom: prenom.trim(),
                nom: nom.trim(),
            };
            if (password) {
                payload.password = password;
            }
            await updateUser(payload);
            setPassword("");
            Alert.alert("Succès", "Votre profil a été mis à jour.");
        } catch (err: any) {
            Alert.alert(
                "Erreur",
                err.message || "Impossible de mettre à jour le profil.",
            );
        } finally {
            setSaving(false);
        }
    };

    const handleLogout = () => {
        Alert.alert(
            "Déconnexion",
            "Êtes-vous sûr de vouloir vous déconnecter ?",
            [
                { text: "Annuler", style: "cancel" },
                {
                    text: "Déconnexion",
                    style: "destructive",
                    onPress: logout,
                },
            ],
        );
    };

    return (
        <ScrollView
            style={styles.container}
            contentContainerStyle={styles.content}
        >
            {/* Profile Header */}
            <View style={styles.headerCard}>
                <View style={styles.avatarCircle}>
                    <Text style={styles.avatarText}>
                        {(
                            user?.prenom?.[0] ||
                            user?.email?.[0] ||
                            "U"
                        ).toUpperCase()}
                    </Text>
                </View>
                <Text style={styles.userName}>
                    {[user?.prenom, user?.nom].filter(Boolean).join(" ") ||
                        user?.email}
                </Text>
                <Text style={styles.userEmail}>{user?.email}</Text>
            </View>

            {/* Profile Edit Card */}
            <View style={styles.card}>
                <Text style={styles.cardTitle}>Mes coordonnées</Text>

                <View style={styles.inputGroup}>
                    <Text style={styles.label}>Prénom</Text>
                    <TextInput
                        style={styles.input}
                        value={prenom}
                        onChangeText={setPrenom}
                        placeholder="Votre prénom"
                        placeholderTextColor={THEME.colors.textMuted}
                    />
                </View>

                <View style={styles.inputGroup}>
                    <Text style={styles.label}>Nom</Text>
                    <TextInput
                        style={styles.input}
                        value={nom}
                        onChangeText={setNom}
                        placeholder="Votre nom"
                        placeholderTextColor={THEME.colors.textMuted}
                    />
                </View>

                <View style={styles.inputGroup}>
                    <Text style={styles.label}>
                        Nouveau mot de passe (optionnel)
                    </Text>
                    <TextInput
                        style={styles.input}
                        value={password}
                        onChangeText={setPassword}
                        placeholder="Laissez vide pour conserver l'actuel"
                        placeholderTextColor={THEME.colors.textMuted}
                        secureTextEntry
                    />
                </View>

                <TouchableOpacity
                    style={[styles.saveBtn, saving && { opacity: 0.6 }]}
                    onPress={handleUpdate}
                    disabled={saving}
                    activeOpacity={0.8}
                >
                    {saving ? (
                        <ActivityIndicator color="#fff" />
                    ) : (
                        <Text style={styles.saveBtnText}>
                            Enregistrer les modifications
                        </Text>
                    )}
                </TouchableOpacity>
            </View>

            {/* Logout Button */}
            <TouchableOpacity
                style={styles.logoutBtn}
                onPress={handleLogout}
                activeOpacity={0.8}
            >
                <Ionicons
                    name="log-out-outline"
                    size={20}
                    color={THEME.colors.danger}
                />
                <Text style={styles.logoutBtnText}>Se déconnecter</Text>
            </TouchableOpacity>
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
    avatarCircle: {
        width: 68,
        height: 68,
        borderRadius: 34,
        backgroundColor: THEME.colors.primaryLight,
        alignItems: "center",
        justifyContent: "center",
        marginBottom: THEME.spacing.md,
    },
    avatarText: {
        fontSize: 28,
        fontWeight: "800",
        color: THEME.colors.primary,
    },
    userName: {
        fontSize: 18,
        fontWeight: "700",
        color: THEME.colors.text,
    },
    userEmail: {
        fontSize: 13,
        color: THEME.colors.textMuted,
        marginTop: 4,
    },
    card: {
        backgroundColor: THEME.colors.card,
        borderRadius: THEME.borderRadius.lg,
        padding: THEME.spacing.lg,
        marginBottom: THEME.spacing.md,
        ...THEME.shadow,
    },
    cardTitle: {
        fontSize: 16,
        fontWeight: "700",
        color: THEME.colors.text,
        marginBottom: THEME.spacing.md,
    },
    inputGroup: {
        marginBottom: THEME.spacing.md,
    },
    label: {
        fontSize: 13,
        fontWeight: "600",
        color: THEME.colors.text,
        marginBottom: 6,
    },
    input: {
        borderWidth: 1,
        borderColor: THEME.colors.border,
        borderRadius: THEME.borderRadius.md,
        paddingHorizontal: 12,
        height: 46,
        fontSize: 14,
        color: THEME.colors.text,
        backgroundColor: "#fafbfc",
    },
    saveBtn: {
        backgroundColor: THEME.colors.primary,
        borderRadius: THEME.borderRadius.md,
        height: 48,
        alignItems: "center",
        justifyContent: "center",
        marginTop: THEME.spacing.sm,
    },
    saveBtnText: {
        color: "#fff",
        fontWeight: "700",
        fontSize: 15,
    },
    logoutBtn: {
        flexDirection: "row",
        alignItems: "center",
        justifyContent: "center",
        backgroundColor: THEME.colors.dangerLight,
        borderRadius: THEME.borderRadius.md,
        height: 48,
        marginTop: THEME.spacing.sm,
    },
    logoutBtnText: {
        color: THEME.colors.danger,
        fontWeight: "700",
        fontSize: 15,
        marginLeft: 8,
    },
});
