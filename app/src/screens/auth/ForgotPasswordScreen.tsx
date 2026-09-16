import { Ionicons } from "@expo/vector-icons";
import React, { useState } from "react";
import {
    ActivityIndicator,
    KeyboardAvoidingView,
    Platform,
    ScrollView,
    StyleSheet,
    Text,
    TextInput,
    TouchableOpacity,
    View,
} from "react-native";
import { THEME } from "../../constants/theme";
import { apiRequest } from "../../services/api";

interface ForgotPasswordScreenProps {
    navigation: any;
}

export const ForgotPasswordScreen: React.FC<ForgotPasswordScreenProps> = ({
    navigation,
}) => {
    const [email, setEmail] = useState("");
    const [loading, setLoading] = useState(false);
    const [successMessage, setSuccessMessage] = useState<string | null>(null);
    const [errorMessage, setErrorMessage] = useState<string | null>(null);

    const handleSubmit = async () => {
        setErrorMessage(null);
        setSuccessMessage(null);

        if (!email.trim()) {
            setErrorMessage("Veuillez saisir votre adresse email.");
            return;
        }

        try {
            setLoading(true);
            const res = await apiRequest("/api/forgot-password", {
                method: "POST",
                body: JSON.stringify({ email: email.trim() }),
            });
            setSuccessMessage(
                res?.message ||
                    "Un email contenant les instructions vous a été envoyé.",
            );
        } catch (err: any) {
            setErrorMessage(
                err.message ||
                    "Impossible d’envoyer la demande de réinitialisation.",
            );
        } finally {
            setLoading(false);
        }
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
                {/* Back Button */}
                <TouchableOpacity
                    style={styles.backButton}
                    onPress={() => navigation.goBack()}
                    activeOpacity={0.7}
                >
                    <Ionicons
                        name="arrow-back"
                        size={22}
                        color={THEME.colors.text}
                    />
                    <Text style={styles.backButtonText}>Retour</Text>
                </TouchableOpacity>

                {/* Card */}
                <View style={styles.card}>
                    <View style={styles.iconCircle}>
                        <Ionicons
                            name="key-outline"
                            size={32}
                            color={THEME.colors.primary}
                        />
                    </View>
                    <Text style={styles.cardTitle}>Mot de passe oublié ?</Text>
                    <Text style={styles.cardDesc}>
                        Indiquez votre adresse email pour recevoir les
                        instructions de réinitialisation de votre mot de passe.
                    </Text>

                    {successMessage ? (
                        <View style={styles.successBanner}>
                            <Ionicons
                                name="checkmark-circle"
                                size={20}
                                color={THEME.colors.success}
                            />
                            <Text style={styles.successBannerText}>
                                {successMessage}
                            </Text>
                        </View>
                    ) : null}

                    {errorMessage ? (
                        <View style={styles.errorBanner}>
                            <Ionicons
                                name="alert-circle"
                                size={20}
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

                    <TouchableOpacity
                        style={[
                            styles.submitButton,
                            loading && styles.submitButtonDisabled,
                        ]}
                        onPress={handleSubmit}
                        disabled={loading}
                        activeOpacity={0.8}
                    >
                        {loading ? (
                            <ActivityIndicator color="#fff" />
                        ) : (
                            <Text style={styles.submitButtonText}>
                                Envoyer les instructions
                            </Text>
                        )}
                    </TouchableOpacity>
                </View>
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
        paddingTop: THEME.spacing.xl,
        paddingBottom: THEME.spacing.xl,
        justifyContent: "center",
    },
    backButton: {
        flexDirection: "row",
        alignItems: "center",
        marginBottom: THEME.spacing.lg,
        alignSelf: "flex-start",
    },
    backButtonText: {
        marginLeft: 6,
        fontSize: 15,
        fontWeight: "600",
        color: THEME.colors.text,
    },
    card: {
        backgroundColor: THEME.colors.card,
        borderRadius: THEME.borderRadius.lg,
        padding: THEME.spacing.lg,
        ...THEME.shadow,
    },
    iconCircle: {
        width: 60,
        height: 60,
        borderRadius: 30,
        backgroundColor: THEME.colors.primaryLight,
        alignItems: "center",
        justifyContent: "center",
        marginBottom: THEME.spacing.md,
        alignSelf: "center",
    },
    cardTitle: {
        fontSize: 22,
        fontWeight: "700",
        color: THEME.colors.text,
        textAlign: "center",
        marginBottom: 8,
    },
    cardDesc: {
        fontSize: 14,
        color: THEME.colors.textMuted,
        textAlign: "center",
        lineHeight: 20,
        marginBottom: THEME.spacing.lg,
    },
    successBanner: {
        flexDirection: "row",
        alignItems: "center",
        backgroundColor: THEME.colors.successLight,
        padding: THEME.spacing.md,
        borderRadius: THEME.borderRadius.md,
        marginBottom: THEME.spacing.md,
    },
    successBannerText: {
        color: THEME.colors.success,
        fontSize: 13,
        marginLeft: 8,
        flex: 1,
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
        marginBottom: THEME.spacing.lg,
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
    submitButton: {
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
    submitButtonDisabled: {
        opacity: 0.6,
    },
    submitButtonText: {
        color: "#fff",
        fontSize: 16,
        fontWeight: "700",
    },
});
