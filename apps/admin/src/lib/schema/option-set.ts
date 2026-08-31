import { z } from 'zod';

/**
 * TypeScript mirror of the PHP `OptionSetSchema` sanitizer. The server is the
 * authoritative validator; this schema keeps the builder form honest and gives
 * us inferred types. Keep the two in lockstep — any field added here must be
 * handled in `includes/Fields/OptionSetSchema.php`.
 */

export const FIELD_TYPES = [
  'text',
  'textarea',
  'number',
  'checkbox',
  'radio',
  'dropdown',
  'swatch',
  'button',
  'heading',
  'date_picker',
  'file_upload',
  'image_upload',
  'color_picker',
] as const;

export type FieldType = (typeof FIELD_TYPES)[number];

export const CHOICE_TYPES: FieldType[] = ['checkbox', 'radio', 'dropdown', 'swatch', 'button'];
export const INPUT_TYPES: FieldType[] = ['text', 'textarea', 'number'];

export const priceSchema = z.object({
  type: z.enum(['none', 'fixed', 'percent']),
  amount: z.number(),
});
export type PriceRule = z.infer<typeof priceSchema>;

export const logicRuleSchema = z.object({
  field: z.string(),
  operator: z.enum(['is', 'is_not', 'empty', 'not_empty']),
  value: z.string(),
});

export const logicSchema = z.object({
  enabled: z.boolean(),
  action: z.enum(['show', 'hide']),
  match: z.enum(['any', 'all']),
  rules: z.array(logicRuleSchema),
});
export type FieldLogic = z.infer<typeof logicSchema>;

export const choiceSchema = z.object({
  id: z.string(),
  label: z.string(),
  value: z.string(),
  default: z.boolean(),
  tooltip: z.string(),
  color: z.string(),
  image: z.string(),
  price: priceSchema,
});
export type FieldChoice = z.infer<typeof choiceSchema>;

export const fieldSchema = z.object({
  id: z.string(),
  type: z.enum(FIELD_TYPES),
  label: z.string(),
  name: z.string(),
  required: z.boolean(),
  placeholder: z.string(),
  tooltip: z.string(),
  default: z.string(),
  logic: logicSchema,

  // Text-only.
  textFormat: z.enum(['text', 'email', 'url', 'regex']).optional(),
  regex: z.string().optional(),

  // Number-only.
  min: z.number().nullable().optional(),
  max: z.number().nullable().optional(),
  step: z.number().nullable().optional(),

  // Input fields carry a flat price on the field itself.
  price: priceSchema.optional(),

  // Choice fields carry a list of selectable options.
  multiple: z.boolean().optional(),
  options: z.array(choiceSchema).optional(),
});
export type Field = z.infer<typeof fieldSchema>;

export const conditionSchema = z.object({
  type: z.enum(['category', 'tag', 'price', 'stock', 'product']),
  operator: z.enum(['is', 'is_not', 'gt', 'lt', 'in_stock', 'out_of_stock']),
  value: z.string(),
});

export const targetingSchema = z.object({
  mode: z.enum(['all', 'manual', 'conditions']),
  productIds: z.array(z.number()),
  match: z.enum(['any', 'all']),
  conditions: z.array(conditionSchema),
});
export type Targeting = z.infer<typeof targetingSchema>;

export const optionSetSchema = z.object({
  id: z.number().optional(),
  name: z.string().min(1),
  status: z.boolean(),
  fields: z.array(fieldSchema),
  targeting: targetingSchema,
});
export type OptionSet = z.infer<typeof optionSetSchema>;

/** Shape returned by the list endpoint. */
export interface OptionSetSummary {
  id: number;
  name: string;
  status: boolean;
  fields: Field[];
  targeting: Targeting;
}
