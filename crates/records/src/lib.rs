use std::collections::BTreeMap;

use serde::{Deserialize, Serialize};
use serde_json::Value;
use time::OffsetDateTime;

use procuchain_shared::{
    ContentHash, DocumentId, DocumentVersionId, DomainError, RulePackSnapshotId, TenantId, UserId,
};

#[derive(Copy, Clone, Debug, PartialEq, Eq, PartialOrd, Ord, Hash, Serialize, Deserialize)]
pub enum DocumentType {
    Ppmp,
    AnnualProcurementPlan,
    BacResolution,
    Contract,
    Other,
}

impl DocumentType {
    pub const fn label(&self) -> &'static str {
        match self {
            Self::Ppmp => "ppmp",
            Self::AnnualProcurementPlan => "annual_procurement_plan",
            Self::BacResolution => "bac_resolution",
            Self::Contract => "contract",
            Self::Other => "other",
        }
    }
}

#[derive(Copy, Clone, Debug, PartialEq, Eq, PartialOrd, Ord, Hash, Serialize, Deserialize)]
pub enum VersionStatus {
    Draft,
    InReview,
    Approved,
    Signed,
    Issued,
    Rejected,
    Superseded,
    Withdrawn,
    Archived,
}

impl VersionStatus {
    pub const fn label(&self) -> &'static str {
        match self {
            Self::Draft => "draft",
            Self::InReview => "in_review",
            Self::Approved => "approved",
            Self::Signed => "signed",
            Self::Issued => "issued",
            Self::Rejected => "rejected",
            Self::Superseded => "superseded",
            Self::Withdrawn => "withdrawn",
            Self::Archived => "archived",
        }
    }

    pub const fn is_immutable(&self) -> bool {
        matches!(
            self,
            Self::Approved
                | Self::Signed
                | Self::Issued
                | Self::Rejected
                | Self::Superseded
                | Self::Withdrawn
                | Self::Archived
        )
    }
}

/// Versioned, structured document content.
///
/// Canonical bytes are produced by sorting object keys recursively and
/// serializing the resulting JSON. Arrays retain order because order is
/// meaningful for plan lines and approvals.
#[derive(Clone, Debug, PartialEq, Eq, Serialize, Deserialize)]
pub struct CanonicalContent {
    fields: BTreeMap<String, Value>,
}

impl CanonicalContent {
    pub fn new(fields: BTreeMap<String, Value>) -> Self {
        Self { fields }
    }

    pub fn from_pairs<I, K, V>(pairs: I) -> Self
    where
        I: IntoIterator<Item = (K, V)>,
        K: Into<String>,
        V: Into<Value>,
    {
        Self {
            fields: pairs
                .into_iter()
                .map(|(key, value)| (key.into(), value.into()))
                .collect(),
        }
    }

    pub fn get(&self, key: &str) -> Option<&Value> {
        self.fields.get(key)
    }

    pub fn fields(&self) -> &BTreeMap<String, Value> {
        &self.fields
    }

    pub fn canonical_bytes(&self) -> Vec<u8> {
        let value = Value::Object(self.fields.clone().into_iter().collect());
        let mut output = Vec::new();
        write_canonical(&value, &mut output);
        output
    }

    pub fn hash(&self) -> ContentHash {
        ContentHash::sha256(&self.canonical_bytes())
    }
}

fn write_canonical(value: &Value, output: &mut Vec<u8>) {
    match value {
        Value::Object(map) => {
            output.push(b'{');
            let mut keys: Vec<&String> = map.keys().collect();
            keys.sort();
            for (index, key) in keys.iter().enumerate() {
                if index > 0 {
                    output.push(b',');
                }
                let encoded_key =
                    serde_json::to_string(key).expect("string keys are always serializable");
                output.extend_from_slice(encoded_key.as_bytes());
                output.push(b':');
                write_canonical(&map[*key], output);
            }
            output.push(b'}');
        }
        Value::Array(items) => {
            output.push(b'[');
            for (index, item) in items.iter().enumerate() {
                if index > 0 {
                    output.push(b',');
                }
                write_canonical(item, output);
            }
            output.push(b']');
        }
        scalar => {
            let encoded = serde_json::to_vec(scalar).expect("JSON scalars are always serializable");
            output.extend_from_slice(&encoded);
        }
    }
}

#[derive(Clone, Debug, PartialEq, Eq, Serialize, Deserialize)]
pub struct TemplateRef {
    template_id: String,
    version: String,
    authority_ref: String,
}

impl TemplateRef {
    pub fn new(
        template_id: impl Into<String>,
        version: impl Into<String>,
        authority_ref: impl Into<String>,
    ) -> Result<Self, DomainError> {
        let template_id = template_id.into();
        let version = version.into();
        let authority_ref = authority_ref.into();
        if template_id.trim().is_empty() {
            return Err(DomainError::rule("template id must not be empty"));
        }
        if version.trim().is_empty() {
            return Err(DomainError::rule("template version must not be empty"));
        }
        if authority_ref.trim().is_empty() {
            return Err(DomainError::rule(
                "template authority reference must not be empty",
            ));
        }
        Ok(Self {
            template_id,
            version,
            authority_ref,
        })
    }

    pub fn template_id(&self) -> &str {
        &self.template_id
    }

    pub fn version(&self) -> &str {
        &self.version
    }

    pub fn authority_ref(&self) -> &str {
        &self.authority_ref
    }
}

#[derive(Clone, Debug, PartialEq, Eq, Serialize, Deserialize)]
pub struct SchemaRef {
    schema_id: String,
    version: String,
}

impl SchemaRef {
    pub fn new(
        schema_id: impl Into<String>,
        version: impl Into<String>,
    ) -> Result<Self, DomainError> {
        let schema_id = schema_id.into();
        let version = version.into();
        if schema_id.trim().is_empty() {
            return Err(DomainError::rule("schema id must not be empty"));
        }
        if version.trim().is_empty() {
            return Err(DomainError::rule("schema version must not be empty"));
        }
        Ok(Self { schema_id, version })
    }

    pub fn schema_id(&self) -> &str {
        &self.schema_id
    }

    pub fn version(&self) -> &str {
        &self.version
    }
}

/// Exact rule-pack snapshot pinned to a document version.
#[derive(Clone, Debug, PartialEq, Eq, Serialize, Deserialize)]
pub struct RulePackRef {
    snapshot_id: RulePackSnapshotId,
    version_label: String,
    manifest_hash: ContentHash,
}

impl RulePackRef {
    pub fn new(
        snapshot_id: RulePackSnapshotId,
        version_label: impl Into<String>,
        manifest_hash: ContentHash,
    ) -> Result<Self, DomainError> {
        let version_label = version_label.into();
        if version_label.trim().is_empty() {
            return Err(DomainError::rule(
                "rule-pack version label must not be empty",
            ));
        }
        Ok(Self {
            snapshot_id,
            version_label,
            manifest_hash,
        })
    }

    pub fn snapshot_id(&self) -> RulePackSnapshotId {
        self.snapshot_id
    }

    pub fn version_label(&self) -> &str {
        &self.version_label
    }

    pub fn manifest_hash(&self) -> &ContentHash {
        &self.manifest_hash
    }
}

#[derive(Clone, Debug, PartialEq, Eq, Serialize, Deserialize)]
pub struct Document {
    id: DocumentId,
    tenant_id: TenantId,
    document_type: DocumentType,
    title: String,
    business_id: Option<String>,
    version_ids: Vec<DocumentVersionId>,
    current_version_id: Option<DocumentVersionId>,
}

impl Document {
    pub fn new(
        id: DocumentId,
        tenant_id: TenantId,
        document_type: DocumentType,
        title: impl Into<String>,
    ) -> Result<Self, DomainError> {
        let title = title.into();
        if title.trim().is_empty() {
            return Err(DomainError::rule("document title must not be empty"));
        }
        Ok(Self {
            id,
            tenant_id,
            document_type,
            title,
            business_id: None,
            version_ids: Vec::new(),
            current_version_id: None,
        })
    }

    pub fn id(&self) -> DocumentId {
        self.id
    }

    pub fn tenant_id(&self) -> TenantId {
        self.tenant_id
    }

    pub fn document_type(&self) -> DocumentType {
        self.document_type
    }

    pub fn title(&self) -> &str {
        &self.title
    }

    pub fn business_id(&self) -> Option<&str> {
        self.business_id.as_deref()
    }

    pub fn set_business_id(&mut self, business_id: impl Into<String>) -> Result<(), DomainError> {
        let business_id = business_id.into();
        if business_id.trim().is_empty() {
            return Err(DomainError::rule("business id must not be empty"));
        }
        self.business_id = Some(business_id);
        Ok(())
    }

    pub fn version_ids(&self) -> &[DocumentVersionId] {
        &self.version_ids
    }

    pub fn current_version_id(&self) -> Option<DocumentVersionId> {
        self.current_version_id
    }

    pub fn register_version(&mut self, version_id: DocumentVersionId) -> Result<u32, DomainError> {
        let version_number = self.version_ids.len() as u32 + 1;
        self.version_ids.push(version_id);
        self.current_version_id = Some(version_id);
        Ok(version_number)
    }
}

#[derive(Clone, Debug, PartialEq, Eq, Serialize, Deserialize)]
pub struct DocumentVersion {
    id: DocumentVersionId,
    document_id: DocumentId,
    version_number: u32,
    status: VersionStatus,
    content: CanonicalContent,
    content_hash: ContentHash,
    template: TemplateRef,
    schema: SchemaRef,
    author: UserId,
    authored_at: OffsetDateTime,
    rule_pack: RulePackRef,
    correction_of: Option<DocumentVersionId>,
    supersedes: Option<DocumentVersionId>,
}

impl DocumentVersion {
    #[allow(clippy::too_many_arguments)]
    pub fn new(
        id: DocumentVersionId,
        document_id: DocumentId,
        version_number: u32,
        content: CanonicalContent,
        template: TemplateRef,
        schema: SchemaRef,
        author: UserId,
        authored_at: OffsetDateTime,
        rule_pack: RulePackRef,
        correction_of: Option<DocumentVersionId>,
        supersedes: Option<DocumentVersionId>,
    ) -> Self {
        let content_hash = content.hash();
        Self {
            id,
            document_id,
            version_number,
            status: VersionStatus::Draft,
            content,
            content_hash,
            template,
            schema,
            author,
            authored_at,
            rule_pack,
            correction_of,
            supersedes,
        }
    }

    pub fn id(&self) -> DocumentVersionId {
        self.id
    }

    pub fn document_id(&self) -> DocumentId {
        self.document_id
    }

    pub fn version_number(&self) -> u32 {
        self.version_number
    }

    pub fn status(&self) -> VersionStatus {
        self.status
    }

    pub fn content(&self) -> &CanonicalContent {
        &self.content
    }

    pub fn content_hash(&self) -> &ContentHash {
        &self.content_hash
    }

    pub fn template(&self) -> &TemplateRef {
        &self.template
    }

    pub fn schema(&self) -> &SchemaRef {
        &self.schema
    }

    pub fn author(&self) -> UserId {
        self.author
    }

    pub fn authored_at(&self) -> OffsetDateTime {
        self.authored_at
    }

    pub fn rule_pack(&self) -> &RulePackRef {
        &self.rule_pack
    }

    pub fn correction_of(&self) -> Option<DocumentVersionId> {
        self.correction_of
    }

    pub fn supersedes(&self) -> Option<DocumentVersionId> {
        self.supersedes
    }

    pub fn is_immutable(&self) -> bool {
        self.status.is_immutable()
    }

    /// Content is stored once and never replaced. Correction creates a new
    /// version and preserves this hash.
    pub fn assert_content_hash(&self, hash: &ContentHash) -> Result<(), DomainError> {
        if &self.content_hash == hash {
            Ok(())
        } else {
            Err(DomainError::ContentHashMismatch)
        }
    }

    pub fn transition_to(
        &mut self,
        expected: &[VersionStatus],
        next: VersionStatus,
    ) -> Result<(), DomainError> {
        if !expected.contains(&self.status) {
            return Err(DomainError::invalid_state(
                "document version",
                self.status.label(),
                expected
                    .iter()
                    .map(VersionStatus::label)
                    .collect::<Vec<_>>()
                    .join(" or "),
            ));
        }
        self.status = next;
        Ok(())
    }

    pub fn set_status(&mut self, status: VersionStatus) {
        self.status = status;
    }
}
