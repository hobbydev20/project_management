import React, {Component} from 'react';
import {Link, Redirect} from "react-router-dom";
import API from './Api.js';
import HeaderTabs from "./HeaderTabs";
import * as Constants from "./Constants";
import EmptyContent from "./EmptyContent";
import {SortableContainer, SortableElement, SortableHandle, arrayMove} from 'react-sortable-hoc';
import DetailedRowItem from "./DetailedRowItem";
import StatusLabel from "./StatusLabel";
import Util from "./Util";
import Dropdown from "./Dropdown";
import Notify from "./Notify";
import Modal from "./Modal";
import TimeTrackView from "./TimeTrackView";
const DragHandle = SortableHandle(() => (
    <a href="#"
       style={{
           padding: "10px 20px 10px 5px",
           cursor: "pointer",
           fontSize: 16,
           color: "#3CA849"
       }}><i
        className="fa fa-arrows-alt"/></a>
));

const Item = SortableElement(({id, type, description, value, amount, loading, onDelete}) => {
    return (<div className={"item" + (loading ? " loading" : "")}>
        <div className="col-md-3">
            <div style={{display: "table"}}>
                <div style={{display: "table-cell"}}>
                    <DragHandle/>
                </div>
                <div style={{display: "table-cell"}}>{type}</div>
            </div>
        </div>
        <div className="col-md-5">{description}</div>
        <div className="col-md-2">{amount}</div>
        <div className="col-md-2">
            <div style={{display: "table"}}>
                <div style={{display: "table-cell", width: "100%"}}>${parseFloat(value).formatMoney()}</div>
                <div style={{display: "table-cell"}}>
                    <a
                        onClick={onDelete.bind(this)}
                        href="#"
                        style={{
                            padding: 10,
                            cursor: "pointer",
                            fontSize: 16,
                            color: "#ED5564"
                        }}><i
                        className="far fa-trash-alt"/></a>
                </div>
            </div>
        </div>
    </div>);
});

const List = SortableContainer(({items, onItemDelete}) => {
    return (
        <div>
            {items.length <= 0 &&
            <EmptyContent
                title="Start building your invoice."
                description={<span>Update your invoice details on the left and start adding new or<br/> existing products and/or services items to your invoice.</span>}/>
            }
            {items.map((item, index) => (
                <Item key={`item-${item.id}`}
                      index={index}
                      onDelete={(e) => onItemDelete(e, item.id)}
                      {...item} />
            ))}
        </div>
    );
});

const Heading = ({id}) => (
    <h2 className="page-title">{id ? `Invoice #${id}` : 'New Invoice'}</h2>
);

export default class EditInvoice extends Component {
    constructor(props) {
        super(props);

        const {id} = props.match.params;

        this.isUnmounted = false;
        this.state = {
            redirect: false,
            loading: true,
            discount: 0,
            sum: 0,
            due: 0,
            newAmount: 0,
            itemId: -1,
            newDescription: "",
            newType: "",
            newQty: 0,
            items: [],
            invoice_id: id,
            status: 'draft',
            company_id: null,
            company: null,
            currency: null,
            issue_date: null,
            due_date: null,
            sent_date: null,
            paid_date: null,
            terms: null,
            subscription_id: null,
            project_id: null,
            is_recurring: false,
            reference: null,
            cc: null,
            tax: 0,
            paid: 0,
            customers: [],
            types: [],
            sending: false,
            notify: false,
            notifyType: null,
            notifyMessage: null,
            receivers: [],
            visibleAddNewModal: false,
            forceModalClose: false
        };
    }

    updateState(state, callback) {
        if (!this.isUnmounted)
            this.setState(state, callback);
    }

    componentWillMount() {
        const {invoice_id} = this.state;

        API.get(`tinvoices/details/${invoice_id}`)
            .then(res => res.data)
            .then(data => this.initialize(data))
            .catch(e => this.onRequestError(e));

        API.get(`clients/data`)
            .then(res => res.data)
            .then(data => this.customersFetched(data));

        API.get(`items/types`)
            .then(res => res.data)
            .then(data => this.typesFetched(data));

        API.get(`clients/data`)
            .then(res => res.data)
            .then(data => this.receiversFetched(data));

    }

    componentWillUnmount() {
        this.isUnmounted = true;
    }

    onRequestError(e) {
        console.error(e);
    }

    initialize(data) {
        if (data.status) {
            const items = data.data.items.map(({item_id, value, amount, name, type, description}) => {
                return {id: item_id, value, amount, type, description, loading: false};
            });

            const {terms, status, issue_date, due_date, discount, company_id, company, reference} = data.data.invoice;

            this.updateState({
                terms,
                status,
                issue_date,
                due_date,
                discount,
                items,
                company_id,
                company,
                reference,
                loading: false
            }, () => this.calculateAmounts());
        }
    }

    customersFetched(data) {
        if (data.status) {
            let customers = data.data.map(customer => {
                return {value: customer.id, text: customer.firstname};
            });

            this.updateState({customers});
        }
    }

    receiversFetched(data) {
        if (data.status) {
            let receivers = data.data.map(customer => {
                return {value: customer.id, text: customer.email};
            });

            this.updateState({receivers});
        }
    }

    typesFetched(data) {
        if (data.status) {
            let types = data.data.map(type => {
                return {value: type.id, text: type.name, newDescription: type.description};
            });

            this.updateState({types});
        }
    }

    updateFieldValue(e) {
        let field = {};

        field[e.target.name] = e.target.value;

        this.updateState(field);
    }

    updateDropdownValue(name, value) {
        if(value == 'timetracking'){
            this.updateState({
                visibleAddNewModal: true
            });
        }else{
            let field = {};

            field[name] = value;
            API.get(`items/types/getone/`+value)
                .then(res => res.data)
                .then(data => this.itemFetched(data));

            this.updateState(field);    
        }
        
    }

    itemFetched(data) {
        this.updateState({newDescription:data.data.description, newQty:1, newAmount: data.data.value, itemId:data.data.id});
    }

    onSortEnd(index) {
        const {oldIndex, newIndex} = index;

        this.updateState({
            items: arrayMove(this.state.items, oldIndex, newIndex),
        });
    };

    onEditFormSubmitted(data) {
        const {invoice_id} = this.state;
        if (data.status) {
            this.updateState({
                forceModalClose: true
            });
            API.get(`tinvoices/details/${invoice_id}`)
            .then(res => res.data)
            .then(data => this.initialize(data))
            .catch(e => this.onRequestError(e));
        } else {
            this.updateState({
                notify: true,
                notifyType: 'error',
                notifyMessage: 'Failed to add Timetrack to Invoice.',
                forceModalClose: true
            });
        }
    }

    addNewItem(e) {
        e.preventDefault();

        let postData = new FormData();
        let {newType, newDescription, newAmount, newQty, items, invoice_id, types,itemId} = this.state;
        let item_id = Math.random();

        types.map(t => {
            if (newType === t.value) {
                newType = t.text;
            }
        });

        items.push({
            id: item_id,
            type: newType,
            description: newDescription,
            value: newAmount,
            amount: newQty,
            loading: true
        });

        postData.set(app.token_name, app.token);
        postData.set('value', newAmount);
        postData.set('qty', newQty);
        postData.set('description', newDescription);
        postData.set('type', newType);
        postData.set('invoice_id', invoice_id);
        postData.set('itemId', itemId);
        API.post('items/create_item', postData, {
            headers: {'Content-Type': 'multipart/form-data'}
        })
            .then(res => res.data)
            .then(data => this.newItemAdded(data.status, data.data, item_id));

        this.updateState({items});
    }

    newItemAdded(status, item, item_id) {
        if(status){
            let {items} = this.state;

            items = items.map((_item) => {

                if (_item.id === item_id) {
                    _item.loading = false;
                    _item.id = item.id;
                }

                return _item;
            });
            this.updateState({
                items,
                newType: '',
                newDescription: '',
                newAmount: 0,
                newQty: 0
            }, () => this.calculateAmounts(true));
        }else{
            let {items} = this.state;
       
            items = items.filter((item) => {
                return item.id !== item_id;
            });
            this.updateState({items});
            
        }
    }

    calculateAmounts(isNew = false) {
        let {items, discount, sum, due} = this.state;

        sum = due = 0;

        items.map((item) => {
            sum += ((item.value) * (item.amount));
        });

        due = sum;

        if (discount > 0) {
            let discount_amount = sum * (discount / 100);
            discount_amount = discount;

            if (due > discount_amount)
                due -= discount_amount;
        }

        if (isNew) {
            let postData = new FormData();

            postData.set(app.token_name, app.token);
            postData.set('outstanding', due);
            postData.set('sum', sum);

            this.updateInvoice(postData);
        }

        this.updateState({
            sum,
            due
        });
    }

    updateInvoice(postData) {
        const {invoice_id} = this.state;

        postData.set(app.token_name, app.token);

        API.post(`invoices/update/${invoice_id}`, postData, {
            headers: {'Content-Type': 'multipart/form-data'}
        })
            .then(res => res.data)
            .then(data => this.invoiceUpdated(data));
    }

    invoiceUpdated(data) {

    }

    onItemDelete(e, id) {
        e.preventDefault();

        let {items} = this.state;

        items = items.map((_item) => {

            if (_item.id === id) {
                _item.loading = true;
            }

            return _item;
        });

        API.delete('items/delete_item/' + id)
            .then(res => res.data)
            .then(data => this.itemDeleted(data, id));

        this.updateState({items});
    }

    itemDeleted(data, id) {
        let {items} = this.state;

        if (data.status) {
            items = items.filter((item) => {
                return item.id !== id;
            });
            this.updateState({items}, () => this.calculateAmounts(true));
        }
    }

    deleteInvoice(e) {
        e.preventDefault();

        this.updateState({
            loading: true
        });

        const {invoice_id} = this.state;

        API.delete(`invoices/delete/${invoice_id}`)
            .then(res => res.data)
            .then(data => this.invoiceDeleted(data));
    }

    invoiceDeleted(data) {
        if (data.status) {
            this.updateState({
                redirect: '/invoices',
            });
        } else {
            this.updateState({
                loading: false
            });
        }
    }

    updateInvoiceField(key, value) {
        let postData = new FormData();

        postData.set(key, value);

        this.updateInvoice(postData);

        if (key === 'discount') {
            this.updateState({discount: value}, () => this.calculateAmounts(true));
        }
    }

    onNewTypeAdd(item) {
        let {types} = this.state;
        let newId = Math.random();

        types.push({
            value: newId,
            text: item
        });

        this.updateState({types});

        var postData = new FormData();

        postData.set(app.token_name, app.token);
        postData.set('name', item);

        API.post(`items/types/add`, postData, {
            headers: {'Content-Type': 'multipart/form-data'}
        })
            .then(res => res.data)
            .then(data => {
                if (data.status) {
                    let {types} = this.state;
                    const {id} = data.type;

                    types = types.map(type => {
                        if (type.value === newId) {
                            type.value = id;
                        }
                        return type;
                    });

                    this.updateState({types});
                }
            });
    }

    sendInvoice(e) {
        e.preventDefault();

        const {invoice_id, sending} = this.state;

        if (sending)
            return;

        this.updateState({
            sending: true
        });

        API.get(`invoices/sendinvoice/${invoice_id}`)
            .then(res => res.data)
            .then(data => {
                this.updateState({
                    sending: false,
                    notify: true,
                    notifyType: data.status ? 'success' : 'error',
                    notifyMessage: data.message
                });
            });
    }

    render() {
        const {
            redirect,
            loading,
            items,
            discount,
            sum,
            due,
            newType,
            newAmount,
            newDescription,
            newQty,
            invoice_id,
            company_id,
            company,
            cc,
            issue_date,
            terms,
            due_date,
            is_recurring,
            status,
            customers,
            types,
            reference,
            sending,
            notify,
            notifyType,
            notifyMessage,
            receivers,
            visibleAddNewModal,
            forceModalClose
        } = this.state;

        const statuses = [
            {value: 'Draft', text: 'Draft'},
            {value: 'Sent', text: 'Sent'},
            {value: 'Paid', text: 'Paid'},
            {value: 'Overdue', text: 'Overdue'},
        ];

        if (redirect) {
            return <Redirect to={redirect} from="/invoices"/>
        }

        return (
            <div>
                <HeaderTabs {...this.props} tabs={Constants.MoneyTabs}/>
                {notify &&
                <Notify
                    onRequestClose={() => this.updateState({notify: false})}
                    type={notifyType}
                    message={notifyMessage}/>
                }
                {visibleAddNewModal &&
                <Modal title="Select Time Track"
                       forceClose={forceModalClose}
                       onRequestClose={() => this.updateState({visibleAddNewModal: false, forceModalClose: false})}>
                        <TimeTrackView
                            invoice_id={invoice_id}
                            beforeSubmit={() => {
                            }}
                            onSubmitted={(d) => this.onEditFormSubmitted(d)}/>
                </Modal>
                }
                {loading &&
                <div className="tab-loader"/>
                }
                {!loading &&
                <div className="col-sm-12  col-md-12 main">
                    <div className="row">
                        <div className="tabb-header">
                            <div className="col-md-6 table-header-left">
                                <Heading id={invoice_id}/>
                            </div>
                            <div className="col-md-6 text-right table-header-right">
                                <div><a className="transparent" target="_blank"
                                        href={`/invoices/preview/${invoice_id}`}><i
                                    className="far fa-download"/></a></div>
                                <div><a className="transparent" target="_blank"
                                        href={`/invoices/preview/${invoice_id}/show`}><i
                                    className="far fa-eye"/></a></div>
                                <div><a className="transparent" href="#" onClick={this.deleteInvoice.bind(this)}><i
                                    className="far fa-trash-alt"/></a></div>
                                <div><a href="#" onClick={this.sendInvoice.bind(this)}
                                        className="btn btn-success">{sending ? 'Sending...' : 'Send to Client'}</a>
                                </div>
                            </div>
                        </div>
                        <div className="clearfix"/>
                        <div className="tabb-content">
                            <div className="row">
                                <div id="invoice-detailed-section" className="col-md-3">
                                    <div className="shadow-box">
                                        <div className="table-head">Details</div>
                                        <div className="invoice-details">
                                            <DetailedRowItem
                                                type="input"
                                                icon="far fa-tag"
                                                label="Reference"
                                                value={reference}
                                                prefix="#"
                                                onUpdate={v => this.updateInvoiceField('reference', v)}/>
                                            <DetailedRowItem
                                                type="select"
                                                icon="far fa-thermometer-quarter"
                                                label="Status"
                                                items={statuses}
                                                isMultiple={false}
                                                value={status}
                                                labelClass={StatusLabel.classes()[status]}
                                                onUpdate={v => this.updateInvoiceField('status', v)}/>
                                            <DetailedRowItem
                                                type="select"
                                                icon="far fa-user"
                                                label="Email"
                                                items={receivers}
                                                isMultiple={false}
                                                showText={true}
                                                value={company_id}
                                                onUpdate={v => this.updateInvoiceField('company_id', v)}/>
                                            <DetailedRowItem
                                                type="select"
                                                icon="far fa-user-plus"
                                                label="Cc/Bcc"
                                                items={receivers}
                                                isMultiple={false}
                                                showText={true}
                                                placeholder="None"
                                                onUpdate={v => this.updateInvoiceField('company_id', v)}/>
                                            <DetailedRowItem
                                                type="select"
                                                icon="far fa-building"
                                                label="Owner"
                                                items={customers}
                                                isMultiple={false}
                                                showText={true}
                                                value={company_id}
                                                placeholder="Choose owner"
                                                onUpdate={v => this.updateInvoiceField('company_id', v)}/>
                                            <DetailedRowItem
                                                type="date"
                                                icon="far fa-calendar"
                                                label="Invoice Date"
                                                value={issue_date ? Util.getDateHuman(issue_date, true) : 'Choose Date'}
                                                onUpdate={v => this.updateInvoiceField('issue_date', v)}/>
                                            <DetailedRowItem
                                                type="textarea"
                                                icon="far fa-balance-scale"
                                                label="Terms"
                                                value={terms ? terms : 'Choose Terms'}
                                                onUpdate={v => this.updateInvoiceField('terms', v)}/>
                                            <DetailedRowItem
                                                type="date"
                                                icon="far fa-calendar-alt"
                                                label="Due Date"
                                                value={due_date ? Util.getDateHuman(due_date, true) : 'Choose Date'}
                                                onUpdate={v => this.updateInvoiceField('due_date', v)}/>
                                            <div className="detail-row hide">
                                                <div className="col-md-9">
                                                    <div className="table-container">
                                                        <div className="table-cell icon"><i
                                                            className="fa fa-credit-card"/></div>
                                                        <div className="table-cell">Accept Payment Cards</div>
                                                    </div>
                                                </div>
                                                <div className="col-md-3 text-right">
                                                    <a id="pay_credit_card" data-toggle="mainmodal" href={`/invoices/propay/${invoice_id}/${parseFloat(due)}?paymentType=card`}>
                                                        <input type="checkbox"/>
                                                    </a>
                                                </div>
                                            </div>
                                            <div className="detail-row hide">
                                                <div className="col-md-9">
                                                    <div className="table-container">
                                                        <div className="table-cell icon"><i
                                                            className="fa fa-university"/></div>
                                                        <div className="table-cell">Accept ACH</div>
                                                    </div>
                                                </div>
                                                <div className="col-md-3 text-right">
                                                    <a id="pay_check" data-toggle="mainmodal" href={`/invoices/propay/${invoice_id}/${parseFloat(due)}?paymentType=ach`}>
                                                        <input type="checkbox"/>
                                                    </a>
                                                </div>
                                            </div>
                                            <DetailedRowItem
                                                type="input"
                                                icon="far fa-tag"
                                                label="Discount"
                                                value={discount ? parseFloat(discount).formatMoney() : '0.00'}
                                                prefix="$"
                                                onUpdate={v => this.updateInvoiceField('discount', v)}/>
                                            <div className="detail-row">
                                                <div className="col-md-6">
                                                    <div className="table-container">
                                                        <div className="table-cell icon"><i
                                                            className="fa fa-repeat"/></div>
                                                        <div className="table-cell">Recurring</div>
                                                    </div>
                                                </div>
                                                <div className="col-md-6 text-right">
                                                    <span className={"label"}>{is_recurring ? 'Yes' : 'No'}</span>
                                                </div>
                                            </div>
                                            <div className="clearfix"/>
                                        </div>
                                    </div>
                                </div>
                                <div id="invoice-items-section" className="col-md-9">
                                    <div className="shadow-box">
                                        <div className="table-head">Items</div>
                                        <div className="new-item-form">
                                            <div className="row" style={{marginBottom: 0}}>
                                                <div className="col-md-3">
                                                    <Dropdown
                                                        name="newType"
                                                        items={types}
                                                        value={newType}
                                                        allowAdd={true}
                                                        placeholder="Choose Item Type"
                                                        newItemPlaceholder="Type a new item…"
                                                        onNewItem={this.onNewTypeAdd.bind(this)}
                                                        onChange={this.updateDropdownValue.bind(this)}
                                                    />
                                                </div>
                                                <div className="col-md-5">
                                                    <input
                                                        onChange={this.updateFieldValue.bind(this)}
                                                        type="text"
                                                        name="newDescription"
                                                        placeholder="Description"
                                                        value={newDescription}
                                                        className="form-control"/>
                                                </div>
                                                <div className="col-md-2">
                                                    <input
                                                        onChange={this.updateFieldValue.bind(this)}
                                                        type="number"
                                                        name="newQty"
                                                        placeholder="Quantity"
                                                        value={newQty > 0 ? newQty : ''}
                                                        className="form-control"/>
                                                </div>
                                                <div className="col-md-2">
                                                    <div style={{display: "table"}}>
                                                        <div style={{display: "table-cell", width: "100%"}}>
                                                            <input
                                                                onChange={this.updateFieldValue.bind(this)}
                                                                type="number"
                                                                name="newAmount"
                                                                placeholder="Amount"
                                                                value={newAmount > 0 ? newAmount : ''}
                                                                className="form-control"/>
                                                        </div>
                                                        <div onClick={this.addNewItem.bind(this)}
                                                             style={{display: "table-cell"}}>
                                                            <a href="#" id="add-new-item"
                                                               style={{
                                                                   padding: "10px 15px",
                                                                   cursor: "pointer",
                                                                   fontSize: 18,
                                                                   color: "#3CA849"
                                                               }}><i
                                                                className="far fa-plus-circle"/></a>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div id="invoice-items">
                                            <List items={items}
                                                  onSortEnd={this.onSortEnd.bind(this)}
                                                  useDragHandle={true}
                                                  onItemDelete={this.onItemDelete.bind(this)}/>
                                        </div>
                                        <div className="new-items-footer">
                                            <div className="row">
                                                <div className="col-md-9 text-right">Discount:</div>
                                                <div className="col-md-3">${parseFloat(discount).formatMoney()}</div>
                                            </div>
                                            <div className="row" style={{marginBottom: 0}}>
                                                <div className="col-md-9 text-right">Total:</div>
                                                <div className="col-md-3">${parseFloat(sum).formatMoney()}</div>
                                            </div>
                                            <div className="row">
                                                <div className="col-md-9 text-right" style={{paddingTop: 5}}>Balance
                                                    Due:
                                                </div>
                                                <div className="col-md-3"
                                                     style={{fontSize: 20, color: "#343434", fontWeight: "bold"}}>
                                                    ${parseFloat(due).formatMoney()}</div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div className="col-md-12" align="right">
                                    <div className="btn-group dropup" style={{marginTop:"20px"}}>
                                        {parseFloat(due) <= 0 &&
                                            <button type="button" className="btn btn-success dropdown-toggle" data-toggle="dropdown" aria-expanded="false" disabled="disabled">Pay Invoice<span className="caret"></span></button>
                                        }
                                        {parseFloat(due) > 0 &&
                                            <button type="button" className="btn btn-success dropdown-toggle" data-toggle="dropdown" aria-expanded="false">Pay Invoice<span className="caret"></span></button>
                                        }
                                        <ul className="dropdown-menu dropdown-menu-right" role="menu">
                                            <li><a id="pay_credit_card" data-toggle="mainmodal" href={`/invoices/propay/${invoice_id}/${parseFloat(due)}?paymentType=card`}>Credit Card</a></li>
                                            <li><a id="pay_check" data-toggle="mainmodal" href={`/invoices/propay/${invoice_id}/${parseFloat(due)}?paymentType=ach`}>ACH</a></li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                }
            </div>
        );
    }
}