import { Ionicons } from "@expo/vector-icons";
import { createBottomTabNavigator } from "@react-navigation/bottom-tabs";
import { NavigationContainer } from "@react-navigation/native";
import { createNativeStackNavigator } from "@react-navigation/native-stack";
import React from "react";
import { ActivityIndicator, StyleSheet, View } from "react-native";

import { useSafeAreaInsets } from "react-native-safe-area-context";

import { THEME } from "../constants/theme";
import { useAuth } from "../context/AuthContext";

import { ForgotPasswordScreen } from "../screens/auth/ForgotPasswordScreen";
import { LoginScreen } from "../screens/auth/LoginScreen";
import { IngredientsScreen } from "../screens/ingredients/IngredientsScreen";
import { ProfileScreen } from "../screens/profile/ProfileScreen";
import { RecetteDetailScreen } from "../screens/recettes/RecetteDetailScreen";
import { RecetteFormScreen } from "../screens/recettes/RecetteFormScreen";
import { RecettesScreen } from "../screens/recettes/RecettesScreen";
import { TimelineScreen } from "../screens/timeline/TimelineScreen";

const Stack = createNativeStackNavigator();
const Tab = createBottomTabNavigator();
const RecettesStack = createNativeStackNavigator();

function RecettesNavigator() {
    return (
        <RecettesStack.Navigator
            screenOptions={{
                headerTintColor: THEME.colors.primary,
                headerTitleStyle: {
                    fontWeight: "700",
                    color: THEME.colors.text,
                },
            }}
        >
            <RecettesStack.Screen
                name="RecettesList"
                component={RecettesScreen}
                options={{ title: "Les recettes" }}
            />
            <RecettesStack.Screen
                name="RecetteDetail"
                component={RecetteDetailScreen}
                options={{ title: "Détail de la recette" }}
            />
            <RecettesStack.Screen
                name="RecetteForm"
                component={RecetteFormScreen}
                options={({ route }: any) => ({
                    title: route.params?.recette
                        ? "Modifier la recette"
                        : "Nouvelle recette",
                })}
            />
        </RecettesStack.Navigator>
    );
}

function MainTabNavigator() {
    const insets = useSafeAreaInsets();

    return (
        <Tab.Navigator
            screenOptions={({ route }) => ({
                headerShown: true,
                headerTitleStyle: {
                    fontWeight: "700",
                    color: THEME.colors.text,
                },
                tabBarActiveTintColor: THEME.colors.primary,
                tabBarInactiveTintColor: THEME.colors.textMuted,
                tabBarStyle: {
                    backgroundColor: THEME.colors.card,
                    borderTopColor: THEME.colors.border,
                    height: 56 + Math.max(insets.bottom, 12),
                    paddingBottom: Math.max(insets.bottom, 8),
                    paddingTop: 6,
                },
                tabBarLabelStyle: {
                    fontSize: 12,
                    fontWeight: "600",
                },
                tabBarIcon: ({ focused, color, size }) => {
                    let iconName: any = "restaurant";
                    if (route.name === "RepasTab") {
                        iconName = focused ? "time" : "time-outline";
                    } else if (route.name === "RecettesTab") {
                        iconName = focused ? "book" : "book-outline";
                    } else if (route.name === "IngredientsTab") {
                        iconName = focused ? "leaf" : "leaf-outline";
                    } else if (route.name === "ProfileTab") {
                        iconName = focused ? "person" : "person-outline";
                    }
                    return <Ionicons name={iconName} size={22} color={color} />;
                },
            })}
        >
            <Tab.Screen
                name="RepasTab"
                component={TimelineScreen}
                options={{
                    title: "Les repas",
                    tabBarLabel: "Repas",
                }}
            />
            <Tab.Screen
                name="RecettesTab"
                component={RecettesNavigator}
                options={{
                    headerShown: false,
                    tabBarLabel: "Recettes",
                }}
            />
            <Tab.Screen
                name="IngredientsTab"
                component={IngredientsScreen}
                options={{
                    title: "Garde-manger",
                    tabBarLabel: "Ingrédients",
                }}
            />
            <Tab.Screen
                name="ProfileTab"
                component={ProfileScreen}
                options={{
                    title: "Mon Compte",
                    tabBarLabel: "Compte",
                }}
            />
        </Tab.Navigator>
    );
}

export const RootNavigator: React.FC = () => {
    const { user, isLoading } = useAuth();

    if (isLoading) {
        return (
            <View style={styles.loadingContainer}>
                <ActivityIndicator size="large" color={THEME.colors.primary} />
            </View>
        );
    }

    return (
        <NavigationContainer>
            <Stack.Navigator screenOptions={{ headerShown: false }}>
                {user ? (
                    <Stack.Screen name="MainApp" component={MainTabNavigator} />
                ) : (
                    <>
                        <Stack.Screen name="Login" component={LoginScreen} />
                        <Stack.Screen
                            name="ForgotPassword"
                            component={ForgotPasswordScreen}
                        />
                    </>
                )}
            </Stack.Navigator>
        </NavigationContainer>
    );
};

const styles = StyleSheet.create({
    loadingContainer: {
        flex: 1,
        alignItems: "center",
        justifyContent: "center",
        backgroundColor: THEME.colors.background,
    },
});
