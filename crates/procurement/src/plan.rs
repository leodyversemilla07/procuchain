use std::collections::BTreeSet;

use serde::{Deserialize, Serialize};
use serde_json::Value;

use procuchain_records::CanonicalContent;
use procuchain_shared::{Amount, DocumentVersionId, DomainError};

#[derive(Copy, Clone, Debug, PartialEq, Eq, Serialize, Deserialize)]
pub enum AppEdition {
    Indicative,
    Final,
}

impl AppEdition {
    pub const fn label(&self) -> &'static str {
        match self {
            Self::Indicative => "indicative",
            Self::Final => "final",
        }
    }
}

#[derive(Clone, Debug, PartialEq, Eq, Serialize, Deserialize)]
pub struct PpmpLine {
    pub line_id: String,
    pub description: String,
    pub quantity: u32,
    pub unit: String,
    pub estimated_cost: Amount,
    pub schedule: String,
    pub mode_consideration: String,
}

#[derive(Clone, Debug, PartialEq, Eq, Serialize, Deserialize)]
pub struct PpmpContent {
    pub fiscal_year: u16,
    pub end_user_unit: String,
    pub project_title: String,
    pub scope: String,
    pub market_scoping_ref: String,
    pub abc_basis: String,
    pub schedule: String,
    pub risks: Vec<String>,
    pub lines: Vec<PpmpLine>,
}

impl PpmpContent {
    pub fn validate(&self) -> Result<(), DomainError> {
        if !(2000..=2100).contains(&self.fiscal_year) {
            return Err(DomainError::rule(
                "PPMP fiscal year is outside the accepted range",
            ));
        }
        require_text("PPMP end-user unit", &self.end_user_unit)?;
        require_text("PPMP project title", &self.project_title)?;
        require_text("PPMP scope", &self.scope)?;
        require_text(
            "PPMP market-scoping evidence reference",
            &self.market_scoping_ref,
        )?;
        require_text("PPMP ABC basis", &self.abc_basis)?;
        require_text("PPMP schedule", &self.schedule)?;
        if self.lines.is_empty() {
            return Err(DomainError::rule("PPMP must contain at least one line"));
        }
        let mut line_ids = BTreeSet::new();
        for line in &self.lines {
            require_text("PPMP line id", &line.line_id)?;
            require_text("PPMP line description", &line.description)?;
            require_text("PPMP line unit", &line.unit)?;
            require_text("PPMP line schedule", &line.schedule)?;
            require_text("PPMP mode consideration", &line.mode_consideration)?;
            if !line_ids.insert(line.line_id.as_str()) {
                return Err(DomainError::duplicate(format!(
                    "PPMP line {} appears more than once",
                    line.line_id
                )));
            }
            if line.quantity == 0 {
                return Err(DomainError::rule(format!(
                    "PPMP line {} must have a positive quantity",
                    line.line_id
                )));
            }
            if line.estimated_cost.centavos() <= 0 {
                return Err(DomainError::rule(format!(
                    "PPMP line {} must have a positive estimated cost",
                    line.line_id
                )));
            }
        }
        Ok(())
    }

    pub fn to_canonical(&self) -> Result<CanonicalContent, DomainError> {
        self.validate()?;
        struct_to_canonical(self, "PPMP")
    }
}

#[derive(Clone, Debug, PartialEq, Eq, Serialize, Deserialize)]
pub struct AppLine {
    pub line_id: String,
    pub source_ppmp_version_id: DocumentVersionId,
    pub source_ppmp_line_id: String,
    pub description: String,
    pub abc: Amount,
    pub schedule: String,
    pub mode: String,
}

#[derive(Clone, Debug, PartialEq, Eq, Serialize, Deserialize)]
pub struct AppContent {
    pub fiscal_year: u16,
    pub edition: AppEdition,
    pub lines: Vec<AppLine>,
    /// Set when a final APP is derived from an approved Indicative APP.
    pub basis_version_id: Option<DocumentVersionId>,
}

impl AppContent {
    pub fn validate(&self) -> Result<(), DomainError> {
        if !(2000..=2100).contains(&self.fiscal_year) {
            return Err(DomainError::rule(
                "APP fiscal year is outside the accepted range",
            ));
        }
        if self.lines.is_empty() {
            return Err(DomainError::rule("APP must contain at least one line"));
        }
        if self.edition == AppEdition::Final && self.basis_version_id.is_none() {
            return Err(DomainError::rule(
                "a final APP must identify the approved APP version it is based on",
            ));
        }
        if self.edition == AppEdition::Indicative && self.basis_version_id.is_some() {
            return Err(DomainError::rule(
                "an indicative APP must not claim a final-APP basis version",
            ));
        }
        let mut line_ids = BTreeSet::new();
        for line in &self.lines {
            require_text("APP line id", &line.line_id)?;
            require_text("APP line description", &line.description)?;
            require_text("APP line schedule", &line.schedule)?;
            require_text("APP line mode", &line.mode)?;
            require_text("APP source PPMP line id", &line.source_ppmp_line_id)?;
            if !line_ids.insert(line.line_id.as_str()) {
                return Err(DomainError::duplicate(format!(
                    "APP line {} appears more than once",
                    line.line_id
                )));
            }
            if line.abc.centavos() <= 0 {
                return Err(DomainError::rule(format!(
                    "APP line {} must have a positive ABC",
                    line.line_id
                )));
            }
        }
        Ok(())
    }

    /// Derive the final APP from an approved Indicative APP.
    ///
    /// The source version is recorded as the basis so downstream review can
    /// compare the final line set against the indicative line set.
    pub fn from_indicative(
        indicative: &AppContent,
        indicative_version_id: DocumentVersionId,
    ) -> Result<Self, DomainError> {
        if indicative.edition != AppEdition::Indicative {
            return Err(DomainError::rule(
                "only an indicative APP can be used as the final-APP basis",
            ));
        }
        indicative.validate()?;
        let content = Self {
            fiscal_year: indicative.fiscal_year,
            edition: AppEdition::Final,
            lines: indicative.lines.clone(),
            basis_version_id: Some(indicative_version_id),
        };
        content.validate()?;
        Ok(content)
    }

    pub fn to_canonical(&self) -> Result<CanonicalContent, DomainError> {
        self.validate()?;
        struct_to_canonical(self, "APP")
    }
}

fn require_text(label: &str, value: &str) -> Result<(), DomainError> {
    if value.trim().is_empty() {
        Err(DomainError::rule(format!("{label} must not be empty")))
    } else {
        Ok(())
    }
}

fn struct_to_canonical<T: Serialize>(
    value: &T,
    label: &str,
) -> Result<CanonicalContent, DomainError> {
    let value = serde_json::to_value(value)
        .map_err(|error| DomainError::rule(format!("{label} could not be serialized: {error}")))?;
    match value {
        Value::Object(fields) => Ok(CanonicalContent::new(fields.into_iter().collect())),
        _ => Err(DomainError::rule(format!(
            "{label} content must serialize to a JSON object"
        ))),
    }
}
