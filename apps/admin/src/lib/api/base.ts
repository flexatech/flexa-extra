import ky from 'ky';

const config = window?.flexaExtra;

export const api = ky.create({
  prefixUrl: `${config.rest_url}${config.rest_base}`,
  headers: {
    'X-WP-Nonce': config.rest_nonce,
  },
  hooks: {
    afterResponse: [
      async (_request, _options, response) => {
        if (response.status === 403) {
          const body = (await response.clone().json().catch(() => null)) as {
            code?: string;
          } | null;
          if (body?.code === 'rest_cookie_invalid_nonce') {
            location.reload();
          }
        }
      },
    ],
  },
  timeout: 20000,
});

export interface ApiResult<T> {
  success: boolean;
  message?: string;
  data?: T;
}

export async function handleResponse<T>(
  response: Response,
  fallbackError: string,
): Promise<ApiResult<T>> {
  const result = (await response.json()) as ApiResult<T>;
  if (!result.success) {
    throw new Error(result.message ?? fallbackError);
  }
  return result;
}
