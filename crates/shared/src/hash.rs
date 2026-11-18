use std::{fmt, str::FromStr};

use serde::{Deserialize, Serialize};
use sha2::{Digest, Sha256};

use crate::error::DomainError;

/// SHA-256 hash of canonical bytes.
///
/// It is cached as lowercase hexadecimal so it can be persisted and compared
/// without re-hashing. Canonicalization rules are versioned by the caller;
/// the current document model uses sorted JSON object keys.
#[derive(Clone, PartialEq, Eq, PartialOrd, Ord, Hash, Serialize, Deserialize)]
#[serde(transparent)]
pub struct ContentHash(String);

impl ContentHash {
    pub fn sha256(bytes: &[u8]) -> Self {
        let digest = Sha256::digest(bytes);
        Self(to_lower_hex(&digest))
    }

    /// Stable starting value for the append-only audit chain.
    pub fn audit_genesis() -> Self {
        Self::sha256(b"procuchain:audit:genesis:v1")
    }

    pub fn from_hex(value: impl Into<String>) -> Result<Self, DomainError> {
        let value = value.into();
        if value.len() == 64 && value.bytes().all(|byte| byte.is_ascii_hexdigit()) {
            Ok(Self(value.to_ascii_lowercase()))
        } else {
            Err(DomainError::rule(
                "a content hash must be exactly 64 hexadecimal characters",
            ))
        }
    }

    pub fn as_str(&self) -> &str {
        &self.0
    }
}

impl fmt::Debug for ContentHash {
    fn fmt(&self, formatter: &mut fmt::Formatter<'_>) -> fmt::Result {
        write!(formatter, "ContentHash({})", self.0)
    }
}

impl fmt::Display for ContentHash {
    fn fmt(&self, formatter: &mut fmt::Formatter<'_>) -> fmt::Result {
        formatter.write_str(&self.0)
    }
}

impl FromStr for ContentHash {
    type Err = DomainError;

    fn from_str(value: &str) -> Result<Self, Self::Err> {
        Self::from_hex(value)
    }
}

fn to_lower_hex(bytes: &[u8]) -> String {
    const HEX: &[u8; 16] = b"0123456789abcdef";
    let mut output = String::with_capacity(bytes.len() * 2);
    for byte in bytes {
        output.push(HEX[(byte >> 4) as usize] as char);
        output.push(HEX[(byte & 0x0f) as usize] as char);
    }
    output
}
