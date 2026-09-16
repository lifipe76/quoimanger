import AsyncStorage from "@react-native-async-storage/async-storage";
import { Platform } from "react-native";

const API_STORAGE_KEY = "@quoimanger_api_url";
const TOKEN_STORAGE_KEY = "@quoimanger_token";

// Default host: 10.0.2.2 for Android emulator, 127.0.0.1 for web / iOS simulator
const getDefaultBaseUrl = () => {
    if (Platform.OS === "android") {
        return "http://10.0.2.2";
    }
    return "http://quoimanger";
    return "http://127.0.0.1";
};

let currentBaseUrl = getDefaultBaseUrl();

export const ApiConfig = {
    getBaseUrl: async (): Promise<string> => {
        try {
            const stored = await AsyncStorage.getItem(API_STORAGE_KEY);
            if (stored) {
                currentBaseUrl = stored;
            }
        } catch {
            // fallback
        }
        return currentBaseUrl;
    },
    setBaseUrl: async (url: string) => {
        currentBaseUrl = url.replace(/\/+$/, "");
        await AsyncStorage.setItem(API_STORAGE_KEY, currentBaseUrl);
    },
    getToken: async (): Promise<string | null> => {
        try {
            return await AsyncStorage.getItem(TOKEN_STORAGE_KEY);
        } catch {
            return null;
        }
    },
    setToken: async (token: string | null) => {
        if (token) {
            await AsyncStorage.setItem(TOKEN_STORAGE_KEY, token);
        } else {
            await AsyncStorage.removeItem(TOKEN_STORAGE_KEY);
        }
    },
};

export async function apiRequest<T = any>(
    path: string,
    options: RequestInit = {},
): Promise<T> {
    const baseUrl = await ApiConfig.getBaseUrl();
    const token = await ApiConfig.getToken();

    const headers: Record<string, string> = {
        Accept: "application/json",
        "Content-Type": "application/json",
        ...((options.headers as Record<string, string>) || {}),
    };

    if (token) {
        headers["Authorization"] = `Bearer ${token}`;
    }

    const url = `${baseUrl}${path.startsWith("/") ? path : `/${path}`}`;

    console.log(url);

    const response = await fetch(url, {
        ...options,
        headers,
    });

    if (response.status === 204) {
        return null as any;
    }

    const responseData = await response.json().catch(() => null);

    if (!response.ok) {
        const errorMsg =
            responseData?.error ||
            responseData?.["hydra:description"] ||
            responseData?.detail ||
            `Erreur HTTP ${response.status}`;
        throw new Error(errorMsg);
    }

    return responseData;
}
