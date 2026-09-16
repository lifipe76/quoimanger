import { Ionicons } from "@expo/vector-icons";
import React, { useEffect, useState } from "react";
import {
    ActivityIndicator,
    Alert,
    KeyboardAvoidingView,
    Modal,
    Platform,
    ScrollView,
    StyleSheet,
    Text,
    TextInput,
    TouchableOpacity,
    View,
} from "react-native";
import { THEME } from "../../constants/theme";
import { useAuth } from "../../context/AuthContext";
import { ApiConfig } from "../../services/api";

interface LoginScreenProps {
    navigation: any;
}

export const LoginScreen: React.FC<LoginScreenProps> = ({ navigation }) => {
    const { login } = useAuth();

    const [email, setEmail] = useState("test@test.com");
    const [password, setPassword] = useState("Testtest1");
    const [showPassword, setShowPassword] = useState(false);
    const [loading, setLoading] = useState(false);
    const [errorMessage, setErrorMessage] = useState<string | null>(null);

    // Server Settings Modal
    const [showSettingsModal, setShowSettingsModal] = useState(false);
    const [serverUrl, setServerUrl] = useState("");

    useEffect(() => {
        ApiConfig.getBaseUrl().then(setServerUrl);
    }, []);

    const handleLogin = async () => {
        setErrorMessage(null);
        if (!email.trim() || !password) {
            setErrorMessage("Veuillez renseigner votre email et mot de passe.");
            return;
        }

        try {
            setLoading(true);
            await login(email.trim(), password);
        } catch (err: any) {
            setErrorMessage(err.message || "Identifiants invalides.");
        } finally {
            setLoading(false);
        }
    };

    const saveServerUrl = async () => {
        if (!serverUrl.trim()) return;
        await ApiConfig.setBaseUrl(serverUrl.trim());
        setShowSettingsModal(false);
        Alert.alert(
            "Succès",
            `URL du serveur mise à jour : ${serverUrl.trim()}`,
        );
    };

    return (
        <KeyboardAvoidingView
            style={styles.container}
            behavior={Platform.OS === "ios" ? "padding" : undefined}
        >
            <ScrollView
                contentContainerStyle={styles.scrollContent}
                keyboardShouldPersistTaps="handled"
            >
                {/* Header Settings Cog */}
                <View style={styles.topBar}>
                    <TouchableOpacity
                        style={styles.settingsButton}
                        onPress={() => setShowSettingsModal(true)}
                        activeOpacity={0.7}
                    >
                        <Ionicons
                            name="settings-outline"
                            size={22}
                            color={THEME.colors.textMuted}
                        />
                    </TouchableOpacity>
                </View>

                {/* Brand Header */}
                <View style={styles.brandContainer}>
                    <View style={styles.logoBadge}>
                        <Text style={styles.logoIcon}>🍽️</Text>
                    </View>
                    <Text style={styles.brandTitle}>QuoiManger</Text>
                    <Text style={styles.brandSubtitle}>
                        Planifiez et savourez vos repas au quotidien
                    </Text>
                </View>

                {/* Login Form Card */}
                <View style={styles.card}>
                    <Text style={styles.cardTitle}>Connexion</Text>

                    {errorMessage ? (
                        <View style={styles.errorBanner}>
                            <Ionicons
                                name="alert-circle"
                                size={18}
                                color={THEME.colors.danger}
                            />
                            <Text style={styles.errorBannerText}>
                                {errorMessage}
                            </Text>
                        </View>
                    ) : null}

                    <View style={styles.inputGroup}>
                        <Text style={styles.label}>Adresse email</Text>
                        <View style={styles.inputContainer}>
                            <Ionicons
                                name="mail-outline"
                                size={20}
                                color={THEME.colors.textMuted}
                                style={styles.inputIcon}
                            />
                            <TextInput
                                style={styles.input}
                                placeholder="nom@exemple.com"
                                placeholderTextColor={THEME.colors.textMuted}
                                autoCapitalize="none"
                                keyboardType="email-address"
                                value={email}
                                onChangeText={setEmail}
                            />
                        </View>
                    </View>

                    <View style={styles.inputGroup}>
                        <Text style={styles.label}>Mot de passe</Text>
                        <View style={styles.inputContainer}>
                            <Ionicons
                                name="lock-closed-outline"
                                size={20}
                                color={THEME.colors.textMuted}
                                style={styles.inputIcon}
                            />
                            <TextInput
                                style={styles.input}
                                placeholder="Votre mot de passe"
                                placeholderTextColor={THEME.colors.textMuted}
                                secureTextEntry={!showPassword}
                                value={password}
                                onChangeText={setPassword}
                            />
                            <TouchableOpacity
                                onPress={() => setShowPassword(!showPassword)}
                                style={styles.eyeButton}
                            >
                                <Ionicons
                                    name={
                                        showPassword
                                            ? "eye-off-outline"
                                            : "eye-outline"
                                    }
                                    size={20}
                                    color={THEME.colors.textMuted}
                                />
                            </TouchableOpacity>
                        </View>
                    </View>

                    <TouchableOpacity
                        style={styles.forgotButton}
                        onPress={() => navigation.navigate("ForgotPassword")}
                    >
                        <Text style={styles.forgotButtonText}>
                            Mot de passe oublié ?
                        </Text>
                    </TouchableOpacity>

                    <TouchableOpacity
                        style={[
                            styles.submitButton,
                            loading && styles.submitButtonDisabled,
                        ]}
                        onPress={handleLogin}
                        disabled={loading}
                        activeOpacity={0.8}
                    >
                        {loading ? (
                            <ActivityIndicator color="#fff" />
                        ) : (
                            <>
                                <Text style={styles.submitButtonText}>
                                    Se connecter
                                </Text>
                                <Ionicons
                                    name="arrow-forward"
                                    size={18}
                                    color="#fff"
                                    style={{ marginLeft: 8 }}
                                />
                            </>
                        )}
                    </TouchableOpacity>
                </View>

                {/* Server Setting Modal */}
                <Modal
                    visible={showSettingsModal}
                    transparent
                    animationType="fade"
                    onRequestClose={() => setShowSettingsModal(false)}
                >
                    <View style={styles.modalOverlay}>
                        <View style={styles.modalCard}>
                            <Text style={styles.modalTitle}>
                                Configuration du serveur
                            </Text>
                            <Text style={styles.modalDesc}>
                                Adresse API du backend QuoiManger (ex:
                                http://10.0.2.2 sur émulateur Android, ou
                                http://IP_LOCALE:8000 sur appareil physique).
                            </Text>
                            <TextInput
                                style={styles.modalInput}
                                value={serverUrl}
                                onChangeText={setServerUrl}
                                placeholder="http://10.0.2.2"
                                autoCapitalize="none"
                            />
                            <View style={styles.modalActions}>
                                <TouchableOpacity
                                    style={[
                                        styles.modalBtn,
                                        styles.modalBtnCancel,
                                    ]}
                                    onPress={() => setShowSettingsModal(false)}
                                >
                                    <Text style={styles.modalBtnCancelText}>
                                        Annuler
                                    </Text>
                                </TouchableOpacity>
                                <TouchableOpacity
                                    style={[
                                        styles.modalBtn,
                                        styles.modalBtnSave,
                                    ]}
                                    onPress={saveServerUrl}
                                >
                                    <Text style={styles.modalBtnSaveText}>
                                        Enregistrer
                                    </Text>
                                </TouchableOpacity>
                            </View>
                        </View>
                    </View>
                </Modal>
            </ScrollView>
        </KeyboardAvoidingView>
    );
};

const styles = StyleSheet.create({
    container: {
        flex: 1,
        backgroundColor: THEME.colors.background,
    },
    scrollContent: {
        flexGrow: 1,
        paddingHorizontal: THEME.spacing.lg,
        paddingBottom: THEME.spacing.xl,
        justifyContent: "center",
    },
    topBar: {
        flexDirection: "row",
        justifyContent: "flex-end",
        paddingTop: THEME.spacing.md,
    },
    settingsButton: {
        padding: THEME.spacing.sm,
        borderRadius: THEME.borderRadius.md,
        backgroundColor: THEME.colors.card,
        ...THEME.shadow,
    },
    brandContainer: {
        alignItems: "center",
        marginBottom: THEME.spacing.xl,
    },
    logoBadge: {
        width: 68,
        height: 68,
        borderRadius: 20,
        backgroundColor: THEME.colors.primaryLight,
        alignItems: "center",
        justifyContent: "center",
        marginBottom: THEME.spacing.sm,
    },
    logoIcon: {
        fontSize: 34,
    },
    brandTitle: {
        fontSize: 28,
        fontWeight: "800",
        color: THEME.colors.primary,
        letterSpacing: -0.5,
    },
    brandSubtitle: {
        fontSize: 14,
        color: THEME.colors.textMuted,
        marginTop: 4,
        textAlign: "center",
    },
    card: {
        backgroundColor: THEME.colors.card,
        borderRadius: THEME.borderRadius.lg,
        padding: THEME.spacing.lg,
        ...THEME.shadow,
    },
    cardTitle: {
        fontSize: 20,
        fontWeight: "700",
        color: THEME.colors.text,
        marginBottom: THEME.spacing.md,
    },
    errorBanner: {
        flexDirection: "row",
        alignItems: "center",
        backgroundColor: THEME.colors.dangerLight,
        padding: THEME.spacing.md,
        borderRadius: THEME.borderRadius.md,
        marginBottom: THEME.spacing.md,
    },
    errorBannerText: {
        color: THEME.colors.danger,
        fontSize: 13,
        marginLeft: 8,
        flex: 1,
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
    inputContainer: {
        flexDirection: "row",
        alignItems: "center",
        borderWidth: 1,
        borderColor: THEME.colors.border,
        borderRadius: THEME.borderRadius.md,
        backgroundColor: "#fafbfc",
        paddingHorizontal: THEME.spacing.md,
    },
    inputIcon: {
        marginRight: 8,
    },
    input: {
        flex: 1,
        height: 48,
        fontSize: 15,
        color: THEME.colors.text,
    },
    eyeButton: {
        padding: 6,
    },
    forgotButton: {
        alignSelf: "flex-end",
        marginBottom: THEME.spacing.lg,
    },
    forgotButtonText: {
        fontSize: 13,
        color: THEME.colors.primary,
        fontWeight: "600",
    },
    submitButton: {
        backgroundColor: THEME.colors.primary,
        borderRadius: THEME.borderRadius.md,
        height: 50,
        flexDirection: "row",
        alignItems: "center",
        justifyContent: "center",
        shadowColor: THEME.colors.primary,
        shadowOffset: { width: 0, height: 4 },
        shadowOpacity: 0.25,
        shadowRadius: 8,
        elevation: 4,
    },
    submitButtonDisabled: {
        opacity: 0.6,
    },
    submitButtonText: {
        color: "#fff",
        fontSize: 16,
        fontWeight: "700",
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
        marginBottom: 8,
    },
    modalDesc: {
        fontSize: 13,
        color: THEME.colors.textMuted,
        lineHeight: 18,
        marginBottom: THEME.spacing.md,
    },
    modalInput: {
        borderWidth: 1,
        borderColor: THEME.colors.border,
        borderRadius: THEME.borderRadius.md,
        paddingHorizontal: 12,
        height: 44,
        fontSize: 14,
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
