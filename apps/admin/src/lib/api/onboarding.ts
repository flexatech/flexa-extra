import { __ } from '@wordpress/i18n';

import { api, handleResponse } from '@/lib/api/base';

export type OnboardingStatus = 'pending' | 'in_progress' | 'completed' | 'dismissed';

export interface OnboardingState {
  version: number;
  status: OnboardingStatus;
  started_at: number | null;
  completed_at: number | null;
  dismissed_at: number | null;
}

const FALLBACK: OnboardingState = {
  version: 1,
  status: 'completed',
  started_at: null,
  completed_at: null,
  dismissed_at: null,
};

/** The state localized on page load, so the guide can render on first paint. */
export function initialOnboarding(): OnboardingState {
  return window.flexaExtra.onboarding ?? FALLBACK;
}

export async function fetchOnboarding(): Promise<OnboardingState> {
  const response = await api.get('onboarding');
  const result = await handleResponse<OnboardingState>(
    response,
    __('Failed to load setup guide', 'flexa-extra'),
  );
  return result.data ?? initialOnboarding();
}

export async function postOnboarding(status: OnboardingStatus): Promise<OnboardingState | undefined> {
  const response = await api.post('onboarding', { json: { status } });
  const result = await handleResponse<OnboardingState>(
    response,
    __('Failed to update setup guide', 'flexa-extra'),
  );
  return result.data;
}
