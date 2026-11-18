//! Provider-neutral content-addressed object storage.

use std::{fs, io, path::PathBuf};

use procuchain_shared::ContentHash;
use thiserror::Error;

#[derive(Debug, Error)]
pub enum StorageError {
    #[error("object storage I/O failed: {0}")]
    Io(#[from] io::Error),
    #[error("object content does not match its declared hash")]
    HashMismatch,
}

#[derive(Clone, Debug, PartialEq, Eq)]
pub struct StoredObject {
    key: String,
    content_hash: ContentHash,
    byte_length: u64,
}

impl StoredObject {
    pub fn key(&self) -> &str {
        &self.key
    }
    pub fn content_hash(&self) -> &ContentHash {
        &self.content_hash
    }
    pub fn byte_length(&self) -> u64 {
        self.byte_length
    }
}

pub trait ObjectStore {
    fn put(&self, content: &[u8]) -> Result<StoredObject, StorageError>;
    fn get(&self, hash: &ContentHash) -> Result<Option<Vec<u8>>, StorageError>;
    fn contains(&self, hash: &ContentHash) -> Result<bool, StorageError>;
}

#[derive(Clone, Debug)]
pub struct FilesystemObjectStore {
    root: PathBuf,
}

impl FilesystemObjectStore {
    pub fn new(root: impl Into<PathBuf>) -> Result<Self, StorageError> {
        let root = root.into();
        fs::create_dir_all(&root)?;
        Ok(Self { root })
    }

    fn path_for(&self, hash: &ContentHash) -> PathBuf {
        let hex = hash.as_str();
        self.root.join(&hex[0..2]).join(&hex[2..])
    }
}

impl ObjectStore for FilesystemObjectStore {
    fn put(&self, content: &[u8]) -> Result<StoredObject, StorageError> {
        let hash = ContentHash::sha256(content);
        let path = self.path_for(&hash);
        if !path.exists() {
            if let Some(parent) = path.parent() {
                fs::create_dir_all(parent)?;
            }
            let temporary = path.with_extension("tmp");
            fs::write(&temporary, content)?;
            fs::rename(temporary, &path)?;
        }
        Ok(StoredObject {
            key: hash.to_string(),
            content_hash: hash,
            byte_length: content.len() as u64,
        })
    }

    fn get(&self, hash: &ContentHash) -> Result<Option<Vec<u8>>, StorageError> {
        match fs::read(self.path_for(hash)) {
            Ok(content) => {
                if ContentHash::sha256(&content) != *hash {
                    return Err(StorageError::HashMismatch);
                }
                Ok(Some(content))
            }
            Err(error) if error.kind() == io::ErrorKind::NotFound => Ok(None),
            Err(error) => Err(error.into()),
        }
    }

    fn contains(&self, hash: &ContentHash) -> Result<bool, StorageError> {
        Ok(self.get(hash)?.is_some())
    }
}

#[cfg(test)]
mod tests {
    use super::*;

    #[test]
    fn filesystem_store_is_content_addressed_and_deduplicates() {
        let root = std::env::temp_dir().join(format!("procuchain-storage-{}", std::process::id()));
        let store = FilesystemObjectStore::new(&root).expect("store initializes");
        let first = store.put(b"immutable evidence").expect("object stores");
        let second = store.put(b"immutable evidence").expect("duplicate stores");
        assert_eq!(first, second);
        assert_eq!(
            store.get(first.content_hash()).expect("get works"),
            Some(b"immutable evidence".to_vec())
        );
        fs::remove_dir_all(root).expect("temporary objects clean up");
    }

    #[test]
    fn missing_objects_are_not_errors() {
        let root =
            std::env::temp_dir().join(format!("procuchain-storage-missing-{}", std::process::id()));
        let store = FilesystemObjectStore::new(&root).expect("store initializes");
        assert_eq!(
            store
                .get(&ContentHash::sha256(b"missing"))
                .expect("get works"),
            None
        );
        fs::remove_dir_all(root).expect("temporary objects clean up");
    }
}
