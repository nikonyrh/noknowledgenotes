NoKnowledgeNotes
=================

Secure and private encryption-oriented data storage API and UI.
It should be up and running at https://noknowledgenotes.nikonyrh.org.

The name was inspired by [zero-knowledge proofs](https://en.wikipedia.org/wiki/Zero-knowledge_proof):
> Zero-knowledge proof or zero-knowledge protocol is a method by which one party (the prover) can prove to another party (the verifier) that a given statement is true, without conveying any information apart from the fact that the statement is indeed true.

The goal of this project is to provide a secure and private platform and language 
independent online data persistence system (powered by HTTPS and JSON). Servers do
not have any knowledge of the stored data since it is always encrypted on the
client side with strong encryption keys, which are pseudo-randomly generated
from user's username and password. Naturally HTTPS should be used when
communicating with the server, but this system has extra protection against
malicious or hacked servers.

New users can store documents without an explicit sign-up step, they can just
type in their desired username and password. If such combination is already used
then that existing document is returned instead. Thus it is critical to use strong
passwords to avoid it being guessed by accident. Spamming of new documents can be
avoided by dynamically making the [proof-of-work](http://en.wikipedia.org/wiki/Proof-of-work_system) challenge more difficult, but this
is not implemented yet. The current implementation requires the client to calculate
about 2^15.6 SHA-256 hashes, which takes 2 - 3 seconds with the included JS library.
To confirm the POW correctess the server has to calculate only 200 SHA-256 values
and confirm that they have the required number of leading zeroes.


Architecture
--------
This repository haves a PHP implementation of the server and a HTML5 + jQuery
implementation of the client side. Currenltly the code is only at a proof of consept
level but should be fully functional and somewhat robust to common error cases.
Currently a user is identified by the username and password combination, and there
can be only one stored document for each such ID. However the client could implement
multi-document storage within the single JSON document.


Client-side steps
--------
Creating a new document:

 1. User types in username and password, encryption keys are derived automatically
 2. User clicks "Load document"
 3. Server responds that the document does not exist, submit a proof-of-work proposal
 4. User is asked whether he wants to create a new document
 5. JavaScript is used to brute-force the SHA256-based POW, upon completion the answer is submitted to the server (takes 2 - 3 seconds)
 6. User can type the note and click "Save" upon completion

Detailed steps on storing a new version of the document:

 1. Compress the plaintext with nto a UTF16 string (uses [lz-string](https://github.com/pieroxy/lz-string/))
 2. Create the JavaScript object, containing compressed contents and metadata (including a list of previous HMAC values and dates)
 3. Stringify the object as JSON
 4. Encrypt it with 256-bit AES (uses [CryptoJS](https://code.google.com/p/crypto-js/))
 5. Calculate the new HMAC value and embed that along with the document
 6. Upload new contents to the server (AJAX PUT call)
 7. Server checks that the put ID matches with the previously stored value, refuses the operation if ids do not match

Deleting a document:

 1. User types the username and password and clicks "Load document", if not loaded already
 2. User empties the input text field
 3. User clicks the "Delete" button
 4. Server checks that the put ID matches with the previously stored value, refuses the operation if ids do not match

Used keys and proposed key derivation
--------
There are four keys being used at once:
- ID for reading the "document" from server
- ID for writing the document back to server
- Symmetric AES encryption key
- HMAC key to detect data tampering done by the server

All keys are generated on the client side and only the minimal set of keys is revealed
to the server or other parties. All keys are derived from the username and password in
the following way:
```
H(mode, value1, value2) = SHA256(mode || SHA256(mode || value1 || salt) || value2)
```

In the current JS code 'mode' is the name of the key, value1 is the username and
value2 is the password. Anyway these settings can be fully customized by the client.


Provable features
--------
- User does not need to sign up, all identities and keys are calculated and verified ad-hoc
- User can share documents with others in a read-only mode without revealing document contents to the server
- Person obtaining a read-only access cannot overwrite it on the server, and even if server allows it they cannot generate the new HMAC so this would not go unnoticed.
- Server cannot read document contents, it can only observe its size (and here data is compressed before encryption)
- Server cannot modify stored content without it being noticed by the client (but it can return older version of the document or delete it)

These are based on following assumptions:
- Only the user knows his username and password, so only he knows all the keys
- Server only knows document's "get" and "put" ID and encrypted contents, but does not know the decryption key or HMAC key so its contents cannot be read or modified
- Upon read-only sharing the receiving person learns only document's get ID and decryption key but not the "put" key, so server should refuse writes. He does not know the HMAC key either so even with a malicious server the file contents cannot be modified without user noticing it


TODO
--------
- Documentation and comments (server & client side code, although code base is quite small)
- Implement read-only document sharing
- Unit tests (server & client side)
- Optional localStorage usage
- Support for multiple storage locations (multi-server mirroring)
- More configuration options


License
-------
Copyright 2014 Niko Nyrhilä

Licensed under the Apache License, Version 2.0 (the "License");
you may not use this file except in compliance with the License.
You may obtain a copy of the License at

    http://www.apache.org/licenses/LICENSE-2.0

Unless required by applicable law or agreed to in writing, software
distributed under the License is distributed on an "AS IS" BASIS,
WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
See the License for the specific language governing permissions and
limitations under the License.
