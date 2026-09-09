export interface FieldGrantState {
  view: boolean;
  edit: boolean;
}

export const normalizeFieldGrant = (grant: FieldGrantState): FieldGrantState => ({
  view: grant.view || grant.edit,
  edit: grant.edit
});

export const toggleFieldEdit = (grant: FieldGrantState, edit: boolean): FieldGrantState => ({
  view: grant.view || edit,
  edit
});

export const toggleFieldView = (grant: FieldGrantState, view: boolean): FieldGrantState => ({
  view,
  edit: view ? grant.edit : false
});
