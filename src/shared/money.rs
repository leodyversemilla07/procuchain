use std::fmt;

use serde::{Deserialize, Serialize};

use crate::shared::error::DomainError;

/// Exact monetary amount. Values are stored in minor units; floating point is
/// never used for procurement amounts.
#[derive(Copy, Clone, Debug, PartialEq, Eq, PartialOrd, Ord, Serialize, Deserialize)]
pub struct Amount {
    centavos: i64,
    currency: Currency,
}

#[derive(Copy, Clone, Debug, PartialEq, Eq, PartialOrd, Ord, Serialize, Deserialize)]
pub enum Currency {
    Php,
}

impl Amount {
    pub fn php_centavos(centavos: i64) -> Result<Self, DomainError> {
        if centavos < 0 {
            return Err(DomainError::rule("amount must not be negative"));
        }
        Ok(Self {
            centavos,
            currency: Currency::Php,
        })
    }

    pub fn php_pesos(pesos: i64) -> Result<Self, DomainError> {
        if pesos < 0 {
            return Err(DomainError::rule("amount must not be negative"));
        }
        let centavos = pesos
            .checked_mul(100)
            .ok_or_else(|| DomainError::rule("amount is too large"))?;
        Self::php_centavos(centavos)
    }

    pub const fn centavos(&self) -> i64 {
        self.centavos
    }

    pub const fn currency(&self) -> Currency {
        self.currency
    }
}

impl Currency {
    pub const fn code(&self) -> &'static str {
        match self {
            Self::Php => "PHP",
        }
    }
}

impl fmt::Display for Amount {
    fn fmt(&self, formatter: &mut fmt::Formatter<'_>) -> fmt::Result {
        write!(
            formatter,
            "{} {}.{:02}",
            self.currency.code(),
            self.centavos / 100,
            self.centavos % 100
        )
    }
}
