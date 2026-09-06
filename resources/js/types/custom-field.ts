/**
 * The type decides which input is rendered and how the answer is shaped, so it
 * is the one value the screens branch on. Kept in step with
 * `App\Enums\CustomFieldType`.
 */
export type CustomFieldType =
    | 'string'
    | 'text'
    | 'numeric'
    | 'float'
    | 'email'
    | 'date'
    | 'datetime'
    | 'dropdown'
    | 'multi_select'
    | 'checkbox'
    | 'radio';

export type CustomFieldEntity = 'test_case' | 'test_suite' | 'test_plan';

/**
 * A field to fill in, with the answer already given.
 *
 * The definition travels with the answer so one component can render any field
 * the project has enabled without the page knowing what they are.
 *
 * `answer` is an array for the multi-value types and a string for the rest,
 * which is exactly the split `type` predicts.
 */
export type CustomFieldInput = {
    id: number;
    name: string;
    label: string;
    type: CustomFieldType;
    options: string[];
    is_required: boolean;
    maximum_length: number;
    answer: string | string[];
};

/** A row of the application-wide catalogue. */
export type CustomFieldSummary = {
    id: number;
    name: string;
    label: string;
    type: string;
    type_label: string;
    entity_type: CustomFieldEntity;
    entity_label: string;
    /** How many projects have it enabled, and how many answers exist anywhere. */
    projects_count: number;
    answers_count: number;
};

export type CustomFieldFormValues = {
    id: number;
    name: string;
    label: string;
    type: CustomFieldType;
    entity_type: CustomFieldEntity;
    options: string[];
    default_value: string | null;
    pattern: string | null;
    minimum_length: number | null;
    maximum_length: number | null;
    projects_count: number;
    answers_count: number;
    /**
     * Once anything has been filled in, the type and the entity are fixed:
     * answers already stored were validated against the definition as it reads
     * now, and reinterpreting them under another type would be a guess.
     */
    is_answered: boolean;
};

/** What the definition form needs in order to offer the choices. */
export type CustomFieldTypeOption = {
    value: CustomFieldType;
    label: string;
    has_options: boolean;
    is_multi_value: boolean;
    uses_length_limits: boolean;
    uses_pattern: boolean;
};

export type CustomFieldEntityOption = {
    value: CustomFieldEntity;
    label: string;
};

/** A field as the per-project assignment screen sees it. */
export type ProjectCustomField = {
    id: number;
    name: string;
    label: string;
    type_label: string;
    entity_type: CustomFieldEntity;
    entity_label: string;
    is_active: boolean;
    sort_order: number;
    required_on_design: boolean;
    required_on_execution: boolean;
    /** This project's answers, which removing the field would take with it. */
    answers_count: number;
};

export type AvailableCustomField = Pick<
    ProjectCustomField,
    'id' | 'name' | 'label' | 'type_label' | 'entity_type' | 'entity_label'
>;
