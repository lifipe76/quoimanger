import React, { createContext, useContext, useEffect, useState } from "react";
import { ApiConfig, apiRequest } from "../services/api";
import { AuthResponse, User } from "../types";

interface AuthContextType {
    user: User | null;
    token: string | null;
    isLoading: boolean;
    login: (email: string, password: string) => Promise<void>;
    logout: () => Promise<void>;
    updateUser: (data: Partial<User> & { password?: string }) => Promise<User>;
    checkAuth: () => Promise<void>;
}

const AuthContext = createContext<AuthContextType | undefined>(undefined);

export const AuthProvider: React.FC<{ children: React.ReactNode }> = ({
    children,
}) => {
    const [user, setUser] = useState<User | null>(null);
    const [token, setToken] = useState<string | null>(null);
    const [isLoading, setIsLoading] = useState<boolean>(true);

    const checkAuth = async () => {
        try {
            const storedToken = await ApiConfig.getToken();
            if (!storedToken) {
                setIsLoading(false);
                return;
            }

            setToken(storedToken);
            const userData = await apiRequest<User>("/api/me");
            setUser(userData);
        } catch {
            await ApiConfig.setToken(null);
            setToken(null);
            setUser(null);
        } finally {
            setIsLoading(false);
        }
    };

    useEffect(() => {
        checkAuth();
    }, []);

    const login = async (email: string, password: string) => {
        const res = await apiRequest<AuthResponse>("/api/login", {
            method: "POST",
            body: JSON.stringify({ email, password }),
        });

        await ApiConfig.setToken(res.token);
        setToken(res.token);
        setUser(res.user);
    };

    const logout = async () => {
        await ApiConfig.setToken(null);
        setToken(null);
        setUser(null);
    };

    const updateUser = async (
        data: Partial<User> & { password?: string },
    ): Promise<User> => {
        const updated = await apiRequest<User>("/api/me", {
            method: "PUT",
            body: JSON.stringify(data),
        });
        setUser(updated);
        return updated;
    };

    return (
        <AuthContext.Provider
            value={{
                user,
                token,
                isLoading,
                login,
                logout,
                updateUser,
                checkAuth,
            }}
        >
            {children}
        </AuthContext.Provider>
    );
};

export const useAuth = () => {
    const context = useContext(AuthContext);
    if (!context) {
        throw new Error("useAuth must be used within an AuthProvider");
    }
    return context;
};
