# RAG LLM System

A Laravel-based Retrieval-Augmented Generation (RAG) system with document upload, embedding, and chat capabilities, using OpenAI and Elasticsearch.

---

## Features

- User authentication (login/register)
- Document upload and processing (PDF, TXT, DOC, DOCX)
  - Enhanced DOCX processing with multiple extraction methods
  - Automatic fallback to alternative extraction if primary method fails
  - Detailed logging for document processing
- Embedding generation via OpenAI
- Storage and retrieval using Elasticsearch
- Chat interface
- Project-based document organization
- Real-time upload progress tracking
- File search and filtering
- Pagination support
- Document deletion capability

---

## Latest Updates (feature/rag-update)

### New Features
- Added project management system
  - Create and manage multiple projects
  - Associate documents with specific projects
  - Project-based document filtering
- Enhanced UI/UX
  - Real-time upload progress tracking
  - Improved file list with pagination
  - Better error handling and notifications
  - Modern Tailwind CSS styling
- **Enhanced Chat Input:** The chat input area on the Ask Question page (`/rag/ask`) now supports multi-line input with Shift+Enter, expands automatically as you type, and becomes scrollable when it reaches a maximum height.
- Improved Document Processing
  - Better DOCX file support using PhpWord library
  - Enhanced text extraction and cleaning
  - Improved chunking algorithm
- Added Search Functionality
  - Search through uploaded documents
  - Filter by project
  - Sort by upload date

### Technical Improvements
- Added new database migrations for projects
- Enhanced RagService with better error handling
- Improved file processing pipeline
- Added caching support
- Better logging and monitoring

---

## Pages

- **Login Page:** `/login`
  - File: `resources/views/auth/login.blade.php`
  - Enter your email and password to access the system.

- **Home Page:** `/home`
  - File: `resources/views/rag/index.blade.php`
  - Main dashboard for uploading and managing documents.
  - Project management interface
  - Document upload with progress tracking
  - File list with search and pagination

---

## Default Login Credentials

After seeding the database, you can use:

- **Email:** `admin@rag.com`
- **Password:** `admin123`

These are set in:
- `database/seeders/AdminUserSeeder.php`
- `database/seeders/UserSeeder.php`

---

## Requirements

- PHP >= 8.2
- Composer
- Node.js & npm
- OpenAI API Key
- Docker & Docker Compose (for Elasticsearch and Kibana)
- PhpWord library (for DOCX processing)

### Installation Commands

**Install Composer:**
```bash
php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
php composer-setup.php
php -r "unlink('composer-setup.php');"
# Move composer.phar to a directory in your PATH, e.g.:
# mv composer.phar /usr/local/bin/composer
```

**Install Node.js & npm:**
- Download from https://nodejs.org/ or use a package manager:
```bash
# On Ubuntu/Debian
sudo apt update && sudo apt install nodejs npm
# On Mac (with Homebrew)
brew install node
```

**Install PhpWord (for DOCX processing):**
```bash
composer require phpoffice/phpword
```

**Start Elasticsearch & Kibana (Docker Compose):**
```bash
docker-compose up --build
```

---

## Setup Instructions

### 1. Clone the repository

```bash
git clone <your-repo-url>
cd rag-system
```

### 2. Install PHP dependencies

```bash
composer install
```

### 3. Install Node.js dependencies

```bash
npm install
```

### 4. Set up environment variables

Copy the example env file and edit as needed:

```bash
cp env .env
```

Edit `.env` to add your OpenAI and Elasticsearch credentials.

### 5. Run database migrations and seeders

```bash
php artisan migrate --seed
```

### 6. Start the development servers

#### Option 1: Using Laravel and Vite (recommended for development)

```bash
php artisan serve
npm run dev
```

#### Option 2: Using Docker Compose (runs PHP, Elasticsearch, and Kibana)

```bash
docker-compose up --build
```

---

## Document Processing

The system supports multiple document formats with enhanced processing capabilities:

### Supported Formats
- PDF (using pdftotext or native PHP parser)
- DOCX (using PhpWord with fallback to ZipArchive)
- TXT (direct text processing)

### Processing Features
- Automatic text extraction
- Content validation
- Detailed logging
- Error handling with fallback methods
- Chunking for optimal embedding generation

---

## Testing RAG Functionality

You can test the RAG (Retrieval-Augmented Generation) functionality directly from the command line using the `src/rag.php` script. This is useful for testing embeddings and QA functionality independently of the web interface.

### 1. Indexing Documents (Generate Embeddings)

To index a document and generate its embeddings in Elasticsearch:

```bash
php src/rag.php index <file_path> <document_id>
```

Example:
```bash
php src/rag.php index ./documents/sample.pdf doc1
```

### 2. Question Answering

To ask questions about the indexed documents:

```bash
php src/rag.php ask "your question here"
```

Example:
```bash
php src/rag.php ask "What are the main points in the document?"
```

Make sure your environment variables (`OPENAI_API_KEY` and `ELASTIC_API_KEY`) are properly set in the `.env` file before running these commands.

---

## Accessing the App

- Visit [http://localhost:8000](http://localhost:8000) for the Laravel backend.
- Visit [http://localhost:5173](http://localhost:5173) for the Vite frontend (if running separately).
- Login with the default credentials above.

---

## Notes

- Make sure Elasticsearch is running and accessible at the URL specified in your `.env`.
- For production, update your credentials and environment variables accordingly.
- Check the Laravel logs (`storage/logs/laravel.log`) for detailed document processing information.

---

## Troubleshooting

### Document Processing Issues
- Check the Laravel logs for detailed processing information
- Verify file permissions and paths
- Ensure all required PHP extensions are installed
- For DOCX files, verify PhpWord installation

### Elasticsearch Issues
- Verify Elasticsearch is running
- Check connection settings in `.env`
- Ensure proper index mapping exists

---

TO RUN BACKEND LOGIC OF RAG USE THESE CMDS:

TO GENERATE EMBEDDINGS FOR A DOCUMENT

```bash
php src/rag_auto.php index data/<filename>
```

This cmd will generate the embeddings.
On http://localhost:5601/app/dev_tools#/console
you can get all the files which are embedded by this command:

```json
GET llphant/_search
{
  "size": 0,
  "aggs": {
    "all_files": {
      "terms": {
        "field": "sourceName.keyword",
        "size": 1000
      }
    }
  }
}
```

To ask a question:
```bash
php src/rag.php ask "What are the main points in the document?"
```