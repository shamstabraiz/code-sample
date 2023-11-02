import "./App.css";
import React, { useRef, useState, useEffect } from "react";
import Button from "react-bootstrap/Button";
import Modal from "react-bootstrap/Modal";
import { ReactReader } from "react-reader";
import { getBooks, deleteBook, saveBookLocation, uploadBook } from "./utils/books";
import { searchWord, getLimitUser, addWordBookmark, searchSentence } from "./utils/words";
import Card from "react-bootstrap/Card";

function Reader() {
  const [BookLoading, setBookLoading] = useState(true);
  const [showupload, setShowUpload] = useState(false);
  const [showWordsModal, setShowWordsModal] = useState(false);
  const [ShowExplainModal, setShowExplainModal] = useState(false);
  const [selections, setSelections] = useState([]);
  const [book, setbook] = useState("");
  const [books, setBooks] = useState([]);
  const [show, setShow] = useState(false);
  const [bookid, setBookid] = useState(0);
  const [UploadLoading, setUploadLoading] = useState(false);
  const [UploadSubmitState, setUploadSubmitState] = useState(false);
  const [ModalActionUpload, setModalActionUpload] = useState(true);
  const [ShowBookModal, setShowBookModal] = useState(false);
  const [OpenedBook, setOpenedBook] = useState(0);
  const [ExplainBtnStatus, setExplainBtnStatus] = useState(false);
  const [gptResponse, setGptResponse] = useState({});
  const [bookmarkBtnStatus, setBookmarkBtnStatus] = useState(false);
  const [ExplainSentenceBtnStatus, setExplainSentenceBtnStatus] = useState(false);
  const [ShowSentenceModal, setShowSentenceModal] = useState(false);
  const [isSentence, setIsSentence] = useState(false);

  const handleCloseUploadModal = () => setShowUpload(false);
  const handleCloseWordsModal = () => setShowWordsModal(false);
  const handleCloseExplainModal = () => setShowExplainModal(false);
  const handleCloseSentenceModalModal = () => setShowSentenceModal(false);
  const handleShowUploadModal = () => {
    setShowUpload(true);
    setUploadSubmitState(false);
  };
  const handleshowWordsModalModal = () => setShowWordsModal(true);
  const handleshowsentenceModalModal = () => setShowSentenceModal(true);
  const handleshowExplainModal = () => setShowExplainModal(true);
  const handleCloseDeleteModal = () => setShow(false);
  const handleShowDeleteModal = (bookid) => {
    setShow(true);
    setBookid(bookid);
  };

  const uploadFile = (e) => {
    var file = book;
    setModalActionUpload(false);
    uploadBook(book).then(function () {
      setModalActionUpload(true);
      handleCloseUploadModal();
      loadBooks();
    });
  };

  const fileChange = (e) => {
    if (e.target.files[0]) {
      setbook(e.target.files[0]);
      setUploadSubmitState(true);
    }
  };

  const handleDelete = () => {
    setUploadLoading(true);
    deleteBook(bookid).then(function () {
      handleCloseDeleteModal();
      setUploadLoading(false);
      loadBooks();
    });
  };

  const loadBooks = async () => {
    setBooks([]);
    setBookLoading(true);
    getBooks().then(function (response) {
      setBooks(response);
      setBookLoading(false);
    });
  };

  const openBook = (id) => {
    setOpenedBook(id);
    setLocation(books[id].book_location);
    setShowBookModal(true);
  };

  const addBookmark = () => {
    setBookmarkBtnStatus(false);
    addWordBookmark(books[OpenedBook].id, gptResponse.word, gptResponse.context, gptResponse.explanation);
  };

  const [location, setLocation] = useState(null);
  const locationChanged = (epubcifi) => {
    setLocation(epubcifi);
    var temp = books;
    temp[OpenedBook].book_location = epubcifi;
    saveLocation(epubcifi);
    setBooks(temp);
  };

  const saveLocation = (location) => {
    saveBookLocation(books[OpenedBook].id, location);
  };

  const renditionRef = useRef(null);

  const showExplanation = () => {
    handleshowWordsModalModal();
  };
  const showExplanationSentence = () => {
    openWord(1,true);
  };

  const openWord = (idx, BookmarkBtnHideStatus = false) => {
    setGptResponse({});
    setBookmarkBtnStatus(false);
    var word = selections[1][idx];
    word = word.split(".")[0];
    word = word.split("'")[0];
    var context = selections[0];
    if (BookmarkBtnHideStatus == false) {
      setIsSentence(false);
      searchWord(word, context).then(function (data) {
        setGptResponse({
          explanation: data.explanation,
          word: word,
          context: context,
        });
        loadLimit();
        setBookmarkBtnStatus(true);
      });
    } else {
      var sentence = selections[2];
      setIsSentence(true);
      searchSentence(sentence, context).then(function (data) {
        setGptResponse({
          explanation: data.explanation,
          word: sentence,
          context: context,
        });
        loadLimit();
        setBookmarkBtnStatus(true);
      });
    }
    handleshowExplainModal();
  };

  async function loadLimit() {
    return getLimitUser().then(function (data) {
      document.getElementById("limit_id").innerHTML = "user search limit " + data.limit;
      return data;
    });
  }
  useEffect(() => {
    if (renditionRef.current) {
      async function setRenderSelection(cfiRange, contents) {
        setExplainBtnStatus(false);
        setExplainSentenceBtnStatus(false);
        var paragraph = renditionRef.current.getRange(cfiRange).toString();
        var words = renditionRef.current
          .getRange(cfiRange)
          .toString()
          .split(/[ \n.\\]+/);
        var sentence = paragraph;
        const limit_data = await loadLimit();
        if (paragraph != "" && paragraph != " " && limit_data !== null && limit_data.limit >= 1) {
          setSelections([paragraph, words, sentence]);
          setExplainBtnStatus(true);
          setExplainSentenceBtnStatus(true);
        } else {
          setSelections([]);
          setExplainBtnStatus(false);
          setExplainSentenceBtnStatus(false);
        }
      }
      renditionRef.current.on("selected", setRenderSelection);
      return () => {
        renditionRef.current.off("selected", setRenderSelection);
      };
    }
  }, [setSelections, selections]);

  useEffect(() => {
    loadBooks();
    loadLimit();
  }, []);
  return (
    <>
      <h3 id="limit_id"></h3>
      <Button variant="primary" onClick={handleShowUploadModal}>
        Upload Book
      </Button>

      <div className="heading">Continue Reading</div>
      <div className="books-recent">
        {BookLoading ? (
          <h3>Loading...</h3>
        ) : books.length < 1 ? (
          "No Books Found"
        ) : (
          books.slice(0, 3).map((book, idx) => (
            <Card style={{ width: "12rem", height: "330px", cursor: "pointer" }} key={book.id} onClick={() => openBook(idx)}>
              <Card.Img variant="top" src={book.book_cover} class="image-book-cover" />
              <Card.Body>
                <Card.Title> {book.book_title}</Card.Title>
              </Card.Body>
            </Card>
          ))
        )}
      </div>

      <Modal show={showupload} onHide={handleCloseUploadModal}>
        <Modal.Header closeButton>
          <Modal.Title>Upload Your Book</Modal.Title>
        </Modal.Header>
        <Modal.Body>{ModalActionUpload ? <input type="file" accept=".epub" onChange={fileChange} /> : <p>Uploading...</p>}</Modal.Body>
        <Modal.Footer>
          {ModalActionUpload ? (
            <>
              <Button variant="secondary" onClick={handleCloseUploadModal}>
                Close
              </Button>
              {UploadSubmitState ? (
                <Button variant="primary" onClick={uploadFile}>
                  Upload
                </Button>
              ) : (
                <Button variant="primary" disabled>
                  Upload
                </Button>
              )}
            </>
          ) : (
            <>
              <Button variant="secondary" disabled>
                Close
              </Button>
              <Button variant="primary" disabled>
                Upload
              </Button>
            </>
          )}
        </Modal.Footer>
      </Modal>

      <Modal show={show} onHide={handleCloseDeleteModal}>
        <Modal.Header closeButton>
          <Modal.Title>Delete Book</Modal.Title>
        </Modal.Header>
        <Modal.Body>{!UploadLoading ? <p>Are You Sure You Want to Delete</p> : <p>Deleting</p>}</Modal.Body>
        <Modal.Footer>
          {!UploadLoading ? (
            <>
              <Button variant="secondary" onClick={handleCloseDeleteModal}>
                Exit
              </Button>
              <Button variant="primary" onClick={handleDelete}>
                Delete
              </Button>
            </>
          ) : (
            <>
              <Button variant="secondary" disabled>
                Exit
              </Button>
              <Button variant="primary" disabled>
                Delete
              </Button>
            </>
          )}
        </Modal.Footer>
      </Modal>

      <div className="heading">Your Books</div>
      <div className="books">
        {BookLoading ? (
          <h3>Loading...</h3>
        ) : books.length < 1 ? (
          "No Books Found"
        ) : (
          books.map((book, idx) => (
            <div className="book-single mb-3" key={book.id}>
              <img src={book.book_cover} width={"150px"} height={"150px"} />
              <div className="book-title">{book.book_title}</div>
              <div className="book-action">
                <Button variant="primary" className="m-3" onClick={() => openBook(idx)}>
                  Open Book
                </Button>
                <Button variant="danger" className="m-3" onClick={() => handleShowDeleteModal(book.id)}>
                  Delete Book
                </Button>
              </div>
            </div>
          ))
        )}
      </div>

      <Modal show={ShowBookModal} onHide={() => setShowBookModal(false)} fullscreen={true} dialogClassName="modal-90w" aria-labelledby="example-custom-modal-styling-title">
        <Modal.Header closeButton style={{ minHeight: "104px", paddingTop: "33px" }}>
          <Modal.Title id="example-custom-modal-styling-title">{books.length > 0 && books[OpenedBook].book_title}</Modal.Title>
          {ExplainBtnStatus == true && (
            <Button variant="primary" className="ml-3" onClick={showExplanation}>
              Explain a word
            </Button>
          )}
          {ExplainSentenceBtnStatus == true && (
            <Button variant="primary" className="ml-3" onClick={showExplanationSentence}>
              Explain sentence
            </Button>
          )}
        </Modal.Header>
        <Modal.Body>
          {books.length > 0 && (
            <ReactReader
              location={location}
              locationChanged={locationChanged}
              url={books[OpenedBook].book_url}
              epubOptions={{
                flow: "scrolled",
              }}
              getRendition={(rendition) => {
                renditionRef.current = rendition;
                rendition.themes.register("custom", {
                  "p:nth-last-child(1)": {
                    "padding-bottom": "100px",
                  },
                });
                rendition.themes.select("custom");
                setSelections([]);
                setExplainBtnStatus(false);
                setExplainSentenceBtnStatus(false);
              }}
            />
          )}
        </Modal.Body>
      </Modal>

      <Modal show={showWordsModal} onHide={handleCloseWordsModal}>
        <Modal.Header closeButton>
          <Modal.Title>Select a word you don't understand</Modal.Title>
        </Modal.Header>
        <Modal.Body>
          <div className="words">
            {selections.length > 1 &&
              selections[1].map((word, idx) =>
                word != "" ? (
                  <Button variant="primary" className="m-1" onClick={() => openWord(idx)}>
                    {word}
                  </Button>
                ) : (
                  <></>
                )
              )}
          </div>
        </Modal.Body>
        <Modal.Footer></Modal.Footer>
      </Modal>

      <Modal show={ShowSentenceModal} onHide={handleCloseSentenceModalModal} size="lg">
        <Modal.Header closeButton>
          <Modal.Title>Select a Sentence you don't understand</Modal.Title>
        </Modal.Header>
        <Modal.Body>
          <div className="words">
            {selections.length > 1 &&
                  <Button variant="primary" className="m-1" onClick={() => openWord(idx, true)}>
                    {selections[2]}
                  </Button>
            }
          </div>
        </Modal.Body>
        <Modal.Footer></Modal.Footer>
      </Modal>

      <Modal centered show={ShowExplainModal} onHide={handleCloseExplainModal}>
        <Modal.Header closeButton>
          <Modal.Title>Explanation</Modal.Title>
        </Modal.Header>
        <Modal.Body>{!gptResponse.explanation ? <h4>Loadng...</h4> : <div>{gptResponse.explanation}</div>}</Modal.Body>
        <>
          {isSentence == false ? (
            <Modal.Footer>
              {bookmarkBtnStatus == true ? (
                <Button onClick={addBookmark} variant="danger">
                  Bookmark Word
                </Button>
              ) : (
                <Button disabled variant="danger">
                  Bookmark Word
                </Button>
              )}
            </Modal.Footer>
          ) : (
            <Modal.Footer></Modal.Footer>
          )}
        </>
      </Modal>
    </>
  );
}

export default Reader;
