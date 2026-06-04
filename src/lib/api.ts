const BASE_URL = process.env.NEXT_PUBLIC_API_BASE_URL ?? "";

export function authHeaders(): HeadersInit {
    const token = localStorage.getItem("konverpro_token");
    return {
        "Content-Type": "application/json",
        Accept: "application/json",
        Authorization: `Bearer ${token}`,
    };
}

export async function apiFetch(path: string, options: RequestInit = {}) {
    const res = await fetch(`${BASE_URL}${path}`, {
        ...options,
        headers: {
            ...authHeaders(),
            ...options.headers,
        },
    });

    if (res.status === 401) {
        localStorage.removeItem("konverpro_token");
        localStorage.removeItem("konverpro_user");
        window.location.href = "/";
    }

    return res;
}
